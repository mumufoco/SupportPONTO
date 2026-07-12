<?php

namespace App\Commands;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\LGPD\DataAnonymizationService;
use App\Services\Biometric\BiometricDataPurgeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * MELHORIA 7: Job Automático de Retenção LGPD
 *
 * Implementa o Art. 15 da LGPD (Lei 13.709/2018) — encerramento do tratamento
 * de dados pessoais após cumprida a finalidade ou período de retenção.
 *
 * Conformidades atendidas:
 * - LGPD Art. 15: Término do tratamento após finalidade atingida
 * - LGPD Art. 16: Conservação permitida pelo prazo previsto em lei
 * - CLT Art. 11: Prescrição trabalhista — mínimo 5 anos de retenção
 * - Portaria MTE 671/2021: Dados de ponto por no mínimo 5 anos
 *
 * Configuração via .env:
 *   LGPD_RETENTION_DAYS = 1825  (padrão: 5 anos = 365 * 5)
 *   LGPD_BIOMETRIC_RETENTION_DAYS = 365  (padrão: 1 ano após desligamento)
 *   NSR_ALERT_EMAIL = admin@empresa.com (para alertas)
 *
 * Cron sugerido (executar às 02:00 todo domingo):
 *   0 2 * * 0 cd /var/www/html && php spark lgpd:retention
 *
 * Usage:
 *   php spark lgpd:retention              (dry-run por padrão)
 *   php spark lgpd:retention --execute    (executa de verdade)
 *   php spark lgpd:retention --report     (apenas relatório)
 */
class LGPDRetentionCleanup extends BaseCommand
{
    protected $group       = 'LGPD';
    protected $name        = 'lgpd:retention';
    protected $description = 'Aplica política de retenção LGPD: anonimiza ex-funcionários além da janela legal e purga dados biométricos sem consentimento.';
    protected $usage       = 'lgpd:retention [--execute] [--report]';
    protected $options     = [
        '--execute' => 'Executa as operações de anonimização (sem esta flag, roda em dry-run).',
        '--report'  => 'Apenas gera relatório sem executar nenhuma ação.',
    ];

    private DataAnonymizationService $anonymizationService;
    private EmployeeModel            $employeeModel;
    private AuditModel               $auditModel;
    private BiometricDataPurgeService $biometricDataPurgeService;

    public function run(array $params): void
    {
        $this->anonymizationService = new DataAnonymizationService();
        $this->employeeModel        = new EmployeeModel();
        $this->auditModel           = new AuditModel();
        $this->biometricDataPurgeService = new BiometricDataPurgeService();

        $isDryRun  = !array_key_exists('execute', $params);
        $isReport  = array_key_exists('report', $params);

        $retentionDays          = (int) env('LGPD_RETENTION_DAYS', 1825);          // 5 anos CLT
        $biometricRetentionDays = (int) env('LGPD_BIOMETRIC_RETENTION_DAYS', 365); // 1 ano biometria

        CLI::write('');
        CLI::write('╔══════════════════════════════════════════════════════╗', 'cyan');
        CLI::write('║         SupportPONTO — Job de Retenção LGPD         ║', 'cyan');
        CLI::write('╚══════════════════════════════════════════════════════╝', 'cyan');
        CLI::write('');
        CLI::write('Configuração:', 'yellow');
        CLI::write("  Retenção geral:     {$retentionDays} dias (" . round($retentionDays / 365, 1) . " anos)");
        CLI::write("  Retenção biométrica:{$biometricRetentionDays} dias");
        CLI::write('  Modo: ' . ($isDryRun ? '🔍 DRY-RUN (use --execute para aplicar)' : '🔴 EXECUÇÃO REAL'), $isDryRun ? 'yellow' : 'red');
        CLI::write('');

        // ── ETAPA 1: Funcionários desligados além da janela de retenção ──────
        $this->processExpiredEmployees($retentionDays, $isDryRun, $isReport);

        // ── ETAPA 2: Dados biométricos sem consentimento ativo ───────────────
        $this->processBiometricPurge($biometricRetentionDays, $isDryRun, $isReport);

        // ── ETAPA 3: Verificar integridade da cadeia de auditoria ─────────────
        $this->verifyAuditIntegrity($isReport);

        // ── ETAPA 4: Registrar execução no audit log ──────────────────────────
        if (!$isDryRun && !$isReport) {
            $this->auditModel->log(
                null,
                'LGPD_RETENTION_JOB_RUN',
                'system',
                null,
                null,
                [
                    'retention_days'           => $retentionDays,
                    'biometric_retention_days' => $biometricRetentionDays,
                    'timestamp'                => date('c'),
                ],
                'Job de retenção LGPD executado automaticamente via cron',
                'info'
            );
        }

        CLI::write('');
        CLI::write('✅ Job concluído em ' . round(microtime(true) - FCPATH, 2) . 's', 'green');
    }

    // ── Etapa 1: Funcionários desligados ─────────────────────────────────────

    private function processExpiredEmployees(int $retentionDays, bool $isDryRun, bool $isReport): void
    {
        CLI::write('━━━ Etapa 1: Funcionários Desligados ━━━', 'cyan');

        $cutoffDate = Time::now()->subDays($retentionDays)->toDateString();

        // Busca funcionários desligados há mais que $retentionDays dias
        // e que ainda NÃO foram anonimizados
        $expired = $this->employeeModel
            ->where('active', false)
            ->where('demission_date <=', $cutoffDate)
            ->where('email NOT LIKE', 'ANONIMIZADO_%')
            ->findAll();

        CLI::write("  Encontrados: " . count($expired) . " funcionários elegíveis para anonimização");
        CLI::write("  (desligados antes de {$cutoffDate})");

        if ($isReport || empty($expired)) {
            if (empty($expired)) {
                CLI::write('  ✅ Nenhum funcionário elegível.', 'green');
            }
            return;
        }

        $processed = 0;
        $errors    = 0;

        foreach ($expired as $employee) {
            $info = "  [{$employee->id}] {$employee->name} — desligado em " . ($employee->demission_date ?? 'N/D');

            if ($isDryRun) {
                CLI::write($info . ' → seria anonimizado', 'yellow');
                continue;
            }

            CLI::write($info . ' → anonimizando...', 'light_gray');

            $result = $this->anonymizationService->anonymizeEmployee(
                (int) $employee->id,
                'lgpd_retention_job',
                "Retenção automática: desligado há mais de {$retentionDays} dias (Art. 15 LGPD / CLT Art. 11)"
            );

            if ($result['success']) {
                $processed++;
                CLI::write("     ✅ Anonimizado com sucesso", 'green');
            } else {
                $errors++;
                CLI::write("     ❌ Erro: " . $result['message'], 'red');
                log_message('error', "[LGPD-Retention] Falha ao anonimizar employee {$employee->id}: " . $result['message']);
            }
        }

        CLI::write('');
        CLI::write("  Resultado: {$processed} anonimizados, {$errors} erros");
    }

    // ── Etapa 2: Purga de dados biométricos ────────────────────────────────────

    private function processBiometricPurge(int $biometricRetentionDays, bool $isDryRun, bool $isReport): void
    {
        CLI::write('');
        CLI::write('━━━ Etapa 2: Dados Biométricos sem Consentimento ━━━', 'cyan');

        $db         = \Config\Database::connect();
        $cutoffDate = Time::now()->subDays($biometricRetentionDays)->toDateString();

        // Funcionários com biometria mas sem consentimento ativo ou revogado
        $query = "
            SELECT e.id, e.name, e.has_face_biometric, e.has_fingerprint_biometric
            FROM employees e
            WHERE (e.has_face_biometric = TRUE OR e.has_fingerprint_biometric = TRUE)
            AND NOT EXISTS (
                SELECT 1 FROM user_consents uc
                WHERE uc.employee_id = e.id
                AND uc.consent_type IN ('biometric_face', 'biometric_fingerprint')
                AND uc.status = 'granted'
                AND uc.revoked_at IS NULL
            )
            AND (e.demission_date IS NULL OR e.demission_date <= ?)
        ";

        $withoutConsent = $db->query($query, [$cutoffDate])->getResult();

        CLI::write("  Encontrados: " . count($withoutConsent) . " funcionários com biometria sem consentimento ativo");

        if ($isReport || empty($withoutConsent)) {
            if (empty($withoutConsent)) {
                CLI::write('  ✅ Nenhum dado biométrico elegível para purga.', 'green');
            }
            return;
        }

        $purged = 0;

        foreach ($withoutConsent as $employee) {
            $info = "  [{$employee->id}] {$employee->name}";

            if ($isDryRun) {
                CLI::write($info . ' → dados biométricos seriam removidos', 'yellow');
                continue;
            }

            $purge = $this->biometricDataPurgeService->purgeEmployee((int) $employee->id, ['face', 'fingerprint'], 'lgpd_retention_cleanup');

            $this->auditModel->log(
                null,
                'LGPD_BIOMETRIC_PURGE',
                'employees',
                (int) $employee->id,
                null,
                ['purge_reason' => 'Consentimento revogado ou ausente', 'cutoff_date' => $cutoffDate],
                "Dados biométricos removidos — sem consentimento ativo (Art. 15 LGPD)",
                'info'
            );

            $purged++;
            CLI::write($info . ' → ✅ dados biométricos removidos', 'green');
        }

        CLI::write("  Resultado: {$purged} funcionários com biometria removida");
    }

    // ── Etapa 3: Verificação de integridade ─────────────────────────────────────

    private function verifyAuditIntegrity(bool $isReport): void
    {
        CLI::write('');
        CLI::write('━━━ Etapa 3: Integridade da Cadeia de Auditoria ━━━', 'cyan');

        $result = $this->auditModel->verifyIntegrity(5000);

        if ($result['valid']) {
            CLI::write("  ✅ Cadeia íntegra — {$result['checked']} registros verificados", 'green');
        } else {
            $tampered = implode(', ', array_slice($result['tampered_ids'], 0, 10));
            CLI::write("  ❌ ADULTERAÇÃO DETECTADA em {$result['checked']} registros!", 'red');
            CLI::write("  IDs adulterados: {$tampered}" . (count($result['tampered_ids']) > 10 ? '...' : ''), 'red');

            log_message('critical',
                '[LGPD-Retention] ADULTERAÇÃO no audit_logs detectada! IDs: ' .
                implode(',', $result['tampered_ids'])
            );

            // Alertar admin
            $adminEmail = env('NSR_ALERT_EMAIL') ?: env('ADMIN_INITIAL_EMAIL');
            if ($adminEmail) {
                try {
                    $email = \Config\Services::email();
                    $email->setTo($adminEmail)
                          ->setSubject('[ALERTA CRÍTICO] Adulteração no audit_logs — SupportPONTO')
                          ->setMessage(
                              "ADULTERAÇÃO DETECTADA no audit_logs em " . date('Y-m-d H:i:s') . "\n\n" .
                              "IDs afetados: " . implode(', ', $result['tampered_ids']) . "\n\n" .
                              "Verificar imediatamente. Possível violação de conformidade MTE/LGPD."
                          );
                    $email->send(false);
                } catch (\Throwable $e) {
                    log_message('error', '[LGPD-Retention] Falha ao enviar alerta: ' . $e->getMessage());
                }
            }
        }
    }
}

<?php

namespace App\Services\Audit;

use App\Models\AuditModel;
use App\Models\TimePunchModel;
use App\Services\Timesheet\NsrComplianceService;
use App\Support\BootstrapEnv;
use CodeIgniter\Database\BaseConnection;

class AuditComplianceService
{
    private readonly TimePunchModel $timePunchModel;
    private readonly NsrComplianceService $nsrComplianceService;
    private readonly AuditModel $auditModel;

    public function __construct(
        private readonly BaseConnection $db,
        ?TimePunchModel $timePunchModel = null,
        ?NsrComplianceService $nsrComplianceService = null,
        ?AuditModel $auditModel = null,
    ) {
        $this->timePunchModel      = $timePunchModel ?? model(TimePunchModel::class);
        $this->nsrComplianceService = $nsrComplianceService ?? NsrComplianceService::createDefault();
        $this->auditModel          = $auditModel ?? new AuditModel();
    }

    public static function createDefault(): self
    {
        return new self(db_connect(), model(TimePunchModel::class), NsrComplianceService::createDefault(), new AuditModel());
    }

    public function complianceSummary(bool $includeChainVerification = true): array
    {
        $compliance = [];
        $nsrSummary = $this->nsrComplianceService->contingencySummary(30);

        $compliance['nsr_continuity']              = ($nsrSummary['nsr_gaps_count'] ?? 0) === 0 && ($nsrSummary['duplicate_nsrs_count'] ?? 0) === 0;
        $compliance['nsr_issues']                  = $nsrSummary['nsr_gaps'] ?? [];
        $compliance['nsr_duplicate_issues']        = $nsrSummary['duplicate_nsrs'] ?? [];
        $compliance['nsr_counter_health']          = $nsrSummary['counter_health'] ?? ['status' => 'error', 'message' => 'Indisponível'];
        $compliance['nsr_fallback_events_count']   = (int) ($nsrSummary['fallback_events_count'] ?? 0);
        $compliance['nsr_latest_fallback_event']   = $nsrSummary['latest_fallback_event'] ?? null;
        $compliance['nsr_contingency_alerts']      = $nsrSummary['alerts'] ?? [];

        // ── Integridade de hash dos registros de ponto ────────────────────────
        $sampleSize       = $this->resolveHashIntegritySampleSize();
        $totalPunches     = (int) $this->db->table('time_punches')->countAllResults();
        $inspectAllPunches = $sampleSize >= $totalPunches;

        $recentPunchesQuery = $this->db->table('time_punches')->orderBy('created_at', 'DESC');
        if (! $inspectAllPunches) {
            $recentPunchesQuery->limit($sampleSize);
        }

        $recentPunches = $recentPunchesQuery->get()->getResult();
        $hashIssues    = 0;
        foreach ($recentPunches as $punch) {
            if (! $this->timePunchModel->verifyHash($punch)) {
                $hashIssues++;
            }
        }

        $effectiveSampleSize = count($recentPunches);
        $compliance['hash_integrity']              = $hashIssues === 0;
        $compliance['hash_issues_count']           = $hashIssues;
        $compliance['hash_integrity_scope']        = $inspectAllPunches ? 'full' : 'sample';
        $compliance['hash_integrity_sample_size']  = $effectiveSampleSize;
        $compliance['hash_integrity_total_punches'] = $totalPunches;
        $compliance['hash_integrity_scope_note']   = $inspectAllPunches
            ? 'Verificação executada sobre todos os registros de ponto disponíveis.'
            : 'Verificação executada sobre amostra recente configurável. Ajuste AUDIT_COMPLIANCE_SAMPLE_SIZE para ampliar a cobertura.';

        // ── Verificação da cadeia de integridade de audit_logs ────────────────
        // FIX MED-1: Executar verifyIntegrity() real, não apenas confirmar existência da infraestrutura.
        if ($includeChainVerification) {
            $chainSampleSize = $this->resolveChainVerificationSampleSize();
            try {
                $chainResult = $this->auditModel->verifyIntegrity($chainSampleSize);

                $compliance['audit_chain_integrity_valid']        = $chainResult['valid'];
                $compliance['audit_chain_checked']                = $chainResult['checked'];
                $compliance['audit_chain_tampered_count']         = count($chainResult['tampered_ids'] ?? []);
                $compliance['audit_chain_tampered_ids']           = $chainResult['tampered_ids'] ?? [];
                $compliance['audit_chain_forensic_required']      = $chainResult['forensic_review_required'] ?? false;
                $compliance['audit_chain_stored_advances']        = $chainResult['stored_checksum_advances'] ?? 0;
                $compliance['audit_chain_anchor_used']            = $chainResult['anchor_cutoff'] ?? null;
                $compliance['audit_chain_continuation_mode']      = $chainResult['continuation_mode'] ?? 'unknown';
                $compliance['audit_chain_verification_error']     = null;
            } catch (\Throwable $e) {
                log_message('error', '[AuditComplianceService] Falha ao verificar cadeia de integridade: ' . $e->getMessage());
                $compliance['audit_chain_integrity_valid']    = null;
                $compliance['audit_chain_verification_error'] = $e->getMessage();
                $compliance['audit_chain_forensic_required']  = true;
            }
        } else {
            $compliance['audit_chain_integrity_valid']    = null;
            $compliance['audit_chain_verification_error'] = 'Verificação de cadeia não executada (parâmetro includeChainVerification=false).';
            $compliance['audit_chain_forensic_required']  = false;
        }

        // ── Infraestrutura de âncoras ─────────────────────────────────────────
        $compliance['audit_chain_anchor_support'] = $this->db->tableExists('audit_chain_anchors');
        $compliance['audit_chain_anchor_count']   = $compliance['audit_chain_anchor_support']
            ? (int) $this->db->table('audit_chain_anchors')->countAllResults()
            : 0;
        $compliance['audit_controlled_maintenance_mode'] = $this->db->DBDriver === 'Postgre';

        // ── Demais indicadores ────────────────────────────────────────────────
        $oldestRecord = $this->db->table('time_punches')->selectMin('punch_time')->get()->getRow();
        $compliance['storage_period']   = $oldestRecord ? $oldestRecord->punch_time : null;
        $compliance['timezone']         = date_default_timezone_get();
        $compliance['timezone_valid']   = in_array($compliance['timezone'], [
            'America/Sao_Paulo', 'America/Fortaleza', 'America/Recife',
            'America/Bahia', 'America/Belem', 'America/Manaus',
            'America/Cuiaba', 'America/Porto_Velho', 'America/Boa_Vista',
            'America/Rio_Branco', 'America/Noronha',
        ], true);

        // Administradores do sistema não são colaboradores (não batem ponto, não
        // têm CTPS/PIS) — precisam ficar fora deste relatório de conformidade
        // trabalhista (Portaria MTE 671/2021), senão aparecem como "colaborador
        // sem PIS" numa auditoria real.
        $missingPIS = $this->db->table('employees')
            ->where('role !=', 'admin')
            ->groupStart()->where('pis IS NULL')->orWhere('pis', '')->groupEnd()
            ->countAllResults();

        $compliance['employees_without_pis'] = $missingPIS;
        $compliance['total_punches']         = $totalPunches;
        $compliance['total_employees']       = $this->db->table('employees')->where('active', true)->where('role !=', 'admin')->countAllResults();

        return $compliance;
    }

    private function resolveHashIntegritySampleSize(): int
    {
        $configured = BootstrapEnv::get('AUDIT_COMPLIANCE_SAMPLE_SIZE', '100', ['audit.complianceSampleSize']);
        $sampleSize = is_numeric($configured) ? (int) $configured : 100;
        return $sampleSize > 0 ? $sampleSize : 100;
    }

    private function resolveChainVerificationSampleSize(): int
    {
        $configured = BootstrapEnv::get('AUDIT_CHAIN_VERIFY_SAMPLE_SIZE', '500', ['audit.chainVerifySampleSize']);
        $sampleSize = is_numeric($configured) ? (int) $configured : 500;
        return $sampleSize > 0 ? $sampleSize : 500;
    }
}

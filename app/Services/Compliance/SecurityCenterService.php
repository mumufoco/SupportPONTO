<?php

namespace App\Services\Compliance;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;

/**
 * Centro de Segurança — verificações reais do estado do sistema.
 *
 * Cada check reflete o estado atual da configuração ou do banco.
 * Status possíveis: 'ok', 'aviso', 'critico'
 */
class SecurityCenterService
{
    private SettingModel  $settingModel;
    private EmployeeModel $employeeModel;
    private AuditModel    $auditModel;

    public function __construct(
        ?SettingModel  $settingModel  = null,
        ?EmployeeModel $employeeModel = null,
        ?AuditModel    $auditModel    = null
    ) {
        $this->settingModel  = $settingModel  ?? new SettingModel();
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->auditModel    = $auditModel    ?? new AuditModel();
    }

    /**
     * Lista de verificações de segurança com status real.
     *
     * @return array<int, array{title:string, desc:string, status:string}>
     */
    public function getChecks(): array
    {
        return [
            $this->checkTwoFactor(),
            $this->checkPermissionDistribution(),
            $this->checkRecentCriticalEvents(),
        ];
    }

    // ── Verificações individuais ───────────────────────────────────────────

    private function checkTwoFactor(): array
    {
        try {
            // Verifica se 2FA está requerido para admins na configuração do sistema
            $twoFactorRequired = $this->settingModel->get('two_factor_required')
                ?? $this->settingModel->get('require_2fa');

            // Conta admins sem 2FA habilitado (risco de segurança)
            $adminsWithout2FA = $this->employeeModel
                ->where('role', 'admin')
                ->where('active', true)
                ->where('two_factor_enabled', false)
                ->countAllResults();

            if ($adminsWithout2FA > 0) {
                return [
                    'title'  => 'Autenticação em dois fatores',
                    'desc'   => "{$adminsWithout2FA} administrador(es) sem 2FA habilitado. Recomendado para acesso privilegiado.",
                    'status' => 'aviso',
                ];
            }

            return [
                'title'  => 'Autenticação em dois fatores',
                'desc'   => 'Todos os administradores com 2FA habilitado. Sessões e autenticação sob controle.',
                'status' => 'ok',
            ];
        } catch (\Throwable $e) {
            log_structured('warning', 'security_center.2fa_check_failed',
                ['error' => $e->getMessage()]);
            return [
                'title'  => 'Autenticação em dois fatores',
                'desc'   => 'Não foi possível verificar o status do 2FA.',
                'status' => 'aviso',
            ];
        }
    }

    private function checkPermissionDistribution(): array
    {
        try {
            $totalAdmins = $this->employeeModel
                ->where('role', 'admin')
                ->where('active', true)
                ->countAllResults();

            $totalAtivos = $this->employeeModel
                ->where('active', true)
                ->countAllResults();

            // Alerta se mais de 20% dos colaboradores ativos são admin
            $adminPercentage = $totalAtivos > 0 ? ($totalAdmins / $totalAtivos * 100) : 0;

            if ($totalAdmins === 0) {
                return [
                    'title'  => 'Distribuição de permissões',
                    'desc'   => 'Nenhum administrador ativo encontrado. Verifique a configuração de acesso.',
                    'status' => 'critico',
                ];
            }

            if ($adminPercentage > 20) {
                return [
                    'title'  => 'Distribuição de permissões',
                    'desc'   => "{$totalAdmins} de {$totalAtivos} usuários ativos com perfil admin (" . round($adminPercentage) . "%). Princípio do menor privilégio recomenda reduzir.",
                    'status' => 'aviso',
                ];
            }

            return [
                'title'  => 'Distribuição de permissões',
                'desc'   => "{$totalAdmins} admin(s) em {$totalAtivos} usuário(s) ativo(s). Perfis distribuídos adequadamente.",
                'status' => 'ok',
            ];
        } catch (\Throwable $e) {
            log_structured('warning', 'security_center.permissions_check_failed',
                ['error' => $e->getMessage()]);
            return [
                'title'  => 'Distribuição de permissões',
                'desc'   => 'Não foi possível verificar a distribuição de permissões.',
                'status' => 'aviso',
            ];
        }
    }

    private function checkRecentCriticalEvents(): array
    {
        try {
            $since = date('Y-m-d H:i:s', strtotime('-30 days'));
            $criticalCount = $this->auditModel
                ->where('level', 'critical')
                ->where('created_at >=', $since)
                ->countAllResults();

            if ($criticalCount >= 10) {
                return [
                    'title'  => 'Eventos críticos (últimos 30 dias)',
                    'desc'   => "{$criticalCount} eventos críticos registrados. Volume elevado — revisar trilha de auditoria.",
                    'status' => 'critico',
                ];
            }

            if ($criticalCount > 0) {
                return [
                    'title'  => 'Eventos críticos (últimos 30 dias)',
                    'desc'   => "{$criticalCount} evento(s) crítico(s) nos últimos 30 dias. Verificação recomendada.",
                    'status' => 'aviso',
                ];
            }

            return [
                'title'  => 'Eventos críticos (últimos 30 dias)',
                'desc'   => 'Nenhum evento crítico nos últimos 30 dias. Auditoria avançada disponível para acompanhamento detalhado.',
                'status' => 'ok',
            ];
        } catch (\Throwable $e) {
            log_structured('warning', 'security_center.critical_events_check_failed',
                ['error' => $e->getMessage()]);
            return [
                'title'  => 'Eventos críticos (últimos 30 dias)',
                'desc'   => 'Não foi possível verificar eventos críticos.',
                'status' => 'aviso',
            ];
        }
    }


    protected function getAdminRatioLimit(): float
    {
        try {
            $value = SettingModel::get('admin_ratio_limit');
            $limit = ($value !== null && $value !== '') ? (float) $value : 0.20;
            return ($limit > 0 && $limit <= 1) ? $limit : 0.20;
        } catch (\Throwable $e) {
            supportponto_log_exception('security_center', 'admin_ratio_limit_load_failed', $e, [
                'service' => __CLASS__,
            ], 'warning');
            return 0.20;
        }
    }
}

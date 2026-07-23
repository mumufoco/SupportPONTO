<?php

namespace App\Services\Operations;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Models\WarningModel;

/**
 * Central de Pendências — dados reais do banco de dados.
 *
 * Cada contagem é derivada de uma query específica, com fallback
 * seguro em caso de erro (retorna 0 sem quebrar a interface).
 */
class PendingCenterService
{
    private JustificationModel $justificationModel;
    private WarningModel       $warningModel;
    private EmployeeModel      $employeeModel;
    private TimePunchModel     $timePunchModel;
    private SettingModel       $settingModel;

    public function __construct(
        ?JustificationModel $justificationModel = null,
        ?WarningModel       $warningModel       = null,
        ?EmployeeModel      $employeeModel      = null,
        ?TimePunchModel     $timePunchModel     = null,
        ?SettingModel       $settingModel       = null
    ) {
        $this->justificationModel = $justificationModel ?? model(JustificationModel::class);
        $this->warningModel       = $warningModel       ?? model(WarningModel::class);
        $this->employeeModel      = $employeeModel      ?? model(EmployeeModel::class);
        $this->timePunchModel     = $timePunchModel     ?? model(TimePunchModel::class);
        $this->settingModel       = $settingModel       ?? model(SettingModel::class);
    }

    /**
     * Retorna os itens de pendência com contagens reais do banco de dados.
     */
    public function getPendingItems(): array
    {
        return [
            [
                'icon'  => 'bi bi-file-earmark-text-fill',
                'count' => $this->countPendingJustifications(),
                'label' => 'Justificativas pendentes',
                'desc'  => 'Solicitações aguardando análise.',
                'url'   => site_url('justifications'),
            ],
            [
                'icon'  => 'bi bi-exclamation-triangle-fill',
                'count' => $this->countUnsignedWarnings(),
                'label' => 'Advertências sem assinatura',
                'desc'  => 'Registros disciplinares aguardando conclusão.',
                'url'   => sp_warning_index_url(),
            ],
            [
                'icon'  => 'bi bi-fingerprint',
                'count' => $this->countEmployeesWithoutBiometric(),
                'label' => 'Biometrias pendentes',
                'desc'  => 'Colaboradores sem cadastro biométrico.',
                'url'   => site_url('biometric/manage'),
            ],
            [
                'icon'  => 'bi bi-clock-history',
                'count' => $this->countInconsistentPunchesToday(),
                'label' => 'Pontos inconsistentes hoje',
                'desc'  => 'Marcações sem par de entrada/saída no dia atual.',
                'url'   => sp_timesheet_history_url(),
            ],
        ];
    }

    // ── Contagens reais ────────────────────────────────────────────────────

    /**
     * Justificativas com status 'pending' aguardando revisão.
     */
    private function countPendingJustifications(): int
    {
        try {
            return $this->justificationModel->getPendingCount();
        } catch (\Throwable $e) {
            log_structured('warning', 'pending_center.justifications_count_failed',
                ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Advertências com status 'pendente-assinatura' (colaborador ainda não assinou).
     */
    private function countUnsignedWarnings(): int
    {
        try {
            return $this->warningModel
                ->where('status', 'pendente-assinatura')
                ->countAllResults();
        } catch (\Throwable $e) {
            log_structured('warning', 'pending_center.warnings_count_failed',
                ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Colaboradores ativos sem nenhum cadastro biométrico (nem facial nem digital).
     * Administradores são excluídos — eles não têm obrigação de cadastro biométrico.
     */
    private function countEmployeesWithoutBiometric(): int
    {
        try {
            return $this->employeeModel
                ->where('active', true)
                ->where('role !=', 'admin')
                ->where('has_face_biometric', false)
                ->where('has_fingerprint_biometric', false)
                ->countAllResults();
        } catch (\Throwable $e) {
            log_structured('warning', 'pending_center.biometric_count_failed',
                ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Colaboradores com pontos abertos hoje (entrada sem saída correspondente).
     * Consulta leve: apenas o dia atual, não todo o histórico.
     */
    private function countInconsistentPunchesToday(): int
    {
        try {
            [$todayStartAt, $tomorrowStartAt] = $this->timePunchModel->getDayBounds(date('Y-m-d'));

            $rows = $this->timePunchModel
                ->select('employee_id', false)
                ->select("SUM(CASE WHEN punch_type IN ('entrada','intervalo_fim','entry','in') THEN 1 ELSE 0 END) AS entry_count", false)
                ->select("SUM(CASE WHEN punch_type IN ('saida','intervalo_inicio','exit','out') THEN 1 ELSE 0 END) AS exit_count", false)
                ->where('punch_time >=', $todayStartAt)
                ->where('punch_time <', $tomorrowStartAt)
                ->groupBy('employee_id')
                ->having("SUM(CASE WHEN punch_type IN ('entrada','intervalo_fim','entry','in') THEN 1 ELSE 0 END) <> SUM(CASE WHEN punch_type IN ('saida','intervalo_inicio','exit','out') THEN 1 ELSE 0 END)", null, false)
                ->findAll();

            return count($rows);
        } catch (\Throwable $e) {
            log_structured('warning', 'pending_center.punch_inconsistency_count_failed',
                ['error' => $e->getMessage()]);
            return 0;
        }
    }

}


<?php

namespace App\Services\Dashboard;

use App\Models\AuditModel;
use App\Models\PendingPunchModel;
use App\Services\Dashboard\Presenters\AdminDashboardViewPresenter;
use App\Services\Timesheet\NsrComplianceService;
use App\Support\Dates\DashboardDateRange;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\TimePunchModel;
use App\Models\WarningModel;

class DashboardAdminService
{
    private const PUNCH_METHOD_LABELS = [
        'codigo'    => 'Código único',
        'cpf'       => 'CPF',
        'facial'    => 'Reconh. Facial',
        'biometria' => 'Biometria',
        'qrcode'    => 'QR Code',
        'manual'    => 'Manual',
    ];

    private const PENDING_PUNCH_SITUATION_LABELS = [
        'equipment_failure'   => 'Falha no equipamento',
        'system_slow'         => 'Sistema lento',
        'camera_inaccessible' => 'Câmera inacessível',
        'biometric_failed'    => 'Biometria falhou',
        'other'                => 'Outro',
    ];

    private const PENDING_PUNCH_TYPE_LABELS = [
        'entrada'          => 'Entrada',
        'saida'            => 'Saída',
        'intervalo_inicio' => 'Início intervalo',
        'intervalo_fim'    => 'Fim intervalo',
    ];

    /** Mapa entre o status real gravado em justifications.status e a chave exibida no dashboard. */
    private const JUSTIFICATION_STATUS_KEYS = [
        'pendente'  => 'pendente',
        'aprovado'  => 'aprovada',
        'rejeitado' => 'reprovada',
    ];

    private readonly EmployeeModel $employeeModel;
    private readonly TimePunchModel $timePunchModel;
    private readonly JustificationModel $justificationModel;
    private readonly WarningModel $warningModel;
    private readonly NotificationModel $notificationModel;
    private readonly AuditModel $auditModel;
    private readonly PendingPunchModel $pendingPunchModel;
    private readonly AdminDashboardViewPresenter $dashboardViewPresenter;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?TimePunchModel $timePunchModel = null,
        ?JustificationModel $justificationModel = null,
        ?WarningModel $warningModel = null,
        ?NotificationModel $notificationModel = null,
        ?AuditModel $auditModel = null,
        ?PendingPunchModel $pendingPunchModel = null,
        ?AdminDashboardViewPresenter $dashboardViewPresenter = null,
    ) {
        $this->employeeModel = $employeeModel ?? model(EmployeeModel::class);
        $this->timePunchModel = $timePunchModel ?? model(TimePunchModel::class);
        $this->justificationModel = $justificationModel ?? model(JustificationModel::class);
        $this->warningModel = $warningModel ?? model(WarningModel::class);
        $this->notificationModel = $notificationModel ?? model(NotificationModel::class);
        $this->auditModel = $auditModel ?? model(AuditModel::class);
        $this->pendingPunchModel = $pendingPunchModel ?? model(PendingPunchModel::class);
        $this->dashboardViewPresenter = $dashboardViewPresenter ?? new AdminDashboardViewPresenter();
    }

    public function buildViewData(object|array|null $currentUser): array
    {
        $userId = (int) $this->userValue($currentUser, 'id', 0);

        $viewData = [
            'currentUser' => $currentUser,
            'statistics' => $this->statistics(),
            'pendingApprovals' => $this->pendingApprovals(),
            'recentActivities' => $this->recentActivities(),
            'systemAlerts' => $this->systemAlerts(),
            'notifications' => $this->userNotifications($userId),
            // BAIXO-05 (auditoria): esta seção de dados vivia como SQL bruto interpolado
            // diretamente na view dashboard/admin.php. Movida para cá (query builder
            // parametrizado) para não deixar lógica de acesso a dados fora de
            // Models/Services — o padrão anterior não era explorável hoje (o valor
            // interpolado vinha de um array de constantes fixas), mas qualquer
            // manutenção futura que o trocasse por parâmetro de URL sem perceber
            // herdaria SQL injection imediatamente.
            '_methodLabels' => self::PUNCH_METHOD_LABELS,
            '_statsJson' => $this->punchMethodStatsJson(),
            '_justSummary' => $this->justificationStatusSummary(),
            '_situationLabels' => self::PENDING_PUNCH_SITUATION_LABELS,
            '_punchTypeLabels' => self::PENDING_PUNCH_TYPE_LABELS,
            '_pendingPunches' => $this->pendingPunchesForDashboard(),
            '_pendingPunchCount' => $this->pendingPunchModel->where('status', 'pending')->countAllResults(),
            'compliance' => $this->complianceSummary(),
        ];

        $viewData['dashboardPresentation'] = $this->dashboardViewPresenter->present($viewData);

        return $viewData;
    }

    private function punchMethodStatsJson(): string
    {
        $periods = [
            'hoje' => static fn ($q) => $q->where('DATE(punch_time) = CURRENT_DATE', null, false),
            '7d'   => fn ($q) => $q->where('punch_time >=', date('Y-m-d H:i:s', strtotime('-7 days'))),
            '30d'  => fn ($q) => $q->where('punch_time >=', date('Y-m-d H:i:s', strtotime('-30 days'))),
            'tudo' => static fn ($q) => $q,
        ];

        $stats = [];
        foreach ($periods as $periodKey => $applyFilter) {
            $query = $applyFilter($this->timePunchModel->select('method, COUNT(*) AS cnt')->groupBy('method'));
            $countsByMethod = [];
            foreach ($query->findAll() as $row) {
                $countsByMethod[$row->method] = (int) $row->cnt;
            }

            $stats[$periodKey] = [];
            foreach (self::PUNCH_METHOD_LABELS as $methodKey => $label) {
                $stats[$periodKey][] = ['method' => $methodKey, 'label' => $label, 'count' => $countsByMethod[$methodKey] ?? 0];
            }
        }

        return json_encode($stats, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** @return array{pendente:int,aprovada:int,reprovada:int} */
    private function justificationStatusSummary(): array
    {
        $summary = ['pendente' => 0, 'aprovada' => 0, 'reprovada' => 0];

        foreach ($this->justificationModel->select('status, COUNT(*) AS cnt')->groupBy('status')->findAll() as $row) {
            $key = self::JUSTIFICATION_STATUS_KEYS[$row->status] ?? null;
            if ($key !== null) {
                $summary[$key] = (int) $row->cnt;
            }
        }

        return $summary;
    }

    /** @return list<array<string,mixed>> */
    private function pendingPunchesForDashboard(): array
    {
        $rows = $this->pendingPunchModel
            ->select('pending_punches.id, pending_punches.employee_id, employees.name AS employee_name, '
                . 'pending_punches.intended_punch_type, pending_punches.intended_time, '
                . 'pending_punches.situation_type, pending_punches.technical_failures_count, '
                . 'pending_punches.justification_text, pending_punches.status')
            ->join('employees', 'employees.id = pending_punches.employee_id')
            ->where('pending_punches.status', 'pending')
            ->orderBy('pending_punches.intended_time', 'DESC')
            ->limit(10)
            ->asArray()
            ->findAll();

        return $rows;
    }

    private function statistics(): array
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();

        // 'total_employees' precisa ser o total real (ativos + inativos), não só os
        // ativos — o card "Colaboradores Ativos" é quem mostra só os ativos. Antes desta
        // correção os dois cards liam a mesma query e "Colaboradores Ativos" sempre
        // aparecia zerado (chave nunca era gerada), o que fazia os dois números do
        // dashboard parecerem inconsistentes entre si.
        $activeEmployees = $this->employeeModel->where('active', true)->where('role !=', 'admin')->countAllResults();
        $inactiveEmployees = $this->employeeModel->where('active', false)->where('role !=', 'admin')->countAllResults();

        return [
            'total_employees' => $activeEmployees + $inactiveEmployees,
            'active_employees' => $activeEmployees,
            'total_inactive' => $inactiveEmployees,
            'pending_registrations' => $this->employeeModel
                ->where('active', false)
                ->where('role !=', 'admin')
                ->where('created_at >=', date('Y-m-d', strtotime('-7 days')))
                ->countAllResults(),
            'punches_today' => $this->timePunchModel
                ->where('punch_time >=', $todayStart)
                ->where('punch_time <', $tomorrowStart)
                ->countAllResults(),
            'punches_month' => $this->timePunchModel
                ->where('punch_time >=', $monthStart)
                ->where('punch_time <', $nextMonthStart)
                ->countAllResults(),
            'pending_justifications' => $this->justificationModel->where('status', 'pendente')->countAllResults(),
            'active_warnings' => $this->warningModel->where('status', 'pendente-assinatura')->countAllResults(),
            'employees_present' => $this->employeesPresent(),
        ];
    }

    private function pendingApprovals(): array
    {
        return [
            'employees' => $this->employeeModel
                ->where('active', false)
                ->where('role !=', 'admin')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->find(),
            'justifications' => $this->justificationModel
                ->select('justifications.*, employees.name as employee_name')
                ->join('employees', 'employees.id = justifications.employee_id')
                ->where('justifications.status', 'pendente')
                ->orderBy('justifications.created_at', 'DESC')
                ->limit(5)
                ->find(),
        ];
    }

    private function recentActivities(): array
    {
        $auditTable = $this->auditModel->getTable();

        return $this->auditModel
            ->select($auditTable . '.*, employees.name as user_name')
            ->join('employees', 'employees.id = ' . $auditTable . '.user_id', 'left')
            ->orderBy($auditTable . '.created_at', 'DESC')
            ->limit(10)
            ->find();
    }

    private function systemAlerts(): array
    {
        $alerts = [];

        $criticalErrors = $this->auditModel
            ->where('level', 'critical')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->countAllResults();

        if ($criticalErrors > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => lang('DashboardAdmin.alertsData.criticalErrors', [$criticalErrors]),
                'link' => '/admin/logs?level=critical',
            ];
        }

        // Admins não têm obrigação de cadastro biométrico — excluir da contagem.
        $noBiometric = $this->employeeModel
            ->where('active', true)
            ->where('role !=', 'admin')
            ->where('has_face_biometric', false)
            ->where('has_fingerprint_biometric', false)
            ->countAllResults();

        if ($noBiometric > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => lang('DashboardAdmin.alertsData.noBiometric', [$noBiometric]),
                'link' => '/employees?filter=no_biometric',
            ];
        }

        return $alerts;
    }

    private function userNotifications(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->notificationModel
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->findAll();
    }

    private function employeesPresent(): int
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        return $this->timePunchModel
            ->select('DISTINCT time_punches.employee_id')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('time_punches.punch_time >=', $todayStart)
            ->where('time_punches.punch_time <', $tomorrowStart)
            ->where('time_punches.punch_type', 'entrada')
            ->countAllResults();
    }

    /**
     * Resumo leve de compliance para o dashboard — reaproveita
     * NsrComplianceService::counterHealth() (uma leitura + um MAX agregado, barato o
     * bastante pra rodar a cada carregamento do painel) e conta PIS pendente com o
     * mesmo critério (role != admin) já usado em AuditComplianceService. Não chama
     * AuditComplianceService::complianceSummary() aqui de propósito — aquele método
     * varre uma amostra de hashes de ponto e verifica a cadeia de auditoria, caro
     * demais para rodar em toda visita ao dashboard; fica reservado para a página
     * dedicada /audit/compliance.
     */
    private function complianceSummary(): array
    {
        $db = \Config\Database::connect();

        $employeesWithoutPis = $db->table('employees')
            ->where('role !=', 'admin')
            ->groupStart()->where('pis IS NULL')->orWhere('pis', '')->groupEnd()
            ->countAllResults();

        try {
            $nsrHealth = NsrComplianceService::createDefault()->counterHealth();
        } catch (\Throwable $e) {
            $nsrHealth = ['status' => 'error', 'message' => 'Não foi possível verificar o contador NSR.'];
        }

        $issues = 0;
        if (($nsrHealth['status'] ?? 'error') !== 'ok') {
            $issues++;
        }
        if ($employeesWithoutPis > 0) {
            $issues++;
        }

        return [
            'employees_without_pis' => $employeesWithoutPis,
            'nsr_health' => $nsrHealth,
            'issues_count' => $issues,
            'status' => $issues > 0 ? 'warning' : 'ok',
        ];
    }

    private function userValue(object|array|null $user, string $key, mixed $default = null): mixed
    {
        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            return $user->{$key} ?? $default;
        }

        return $default;
    }
}

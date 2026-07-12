<?php

namespace App\Services\Dashboard;

use App\Models\AuditModel;
use App\Models\NotificationModel;

class DashboardDpoService
{
    public function __construct(
        private ?AuditModel $auditModel = null,
        private ?NotificationModel $notificationModel = null,
    ) {
        $this->auditModel ??= model(AuditModel::class);
        $this->notificationModel ??= model(NotificationModel::class);
    }

    public function buildViewData(object|array|null $currentUser): array
    {
        $userId = (int) $this->userValue($currentUser, 'id', 0);

        return [
            'currentUser' => $currentUser,
            'dashboardPresentation' => [
                'pageHeader' => [
                    'title' => 'Painel de conformidade e auditoria',
                    'subtitle' => 'Acesso rápido às trilhas, LGPD e relatórios de compliance.',
                    'icon' => 'bi bi-shield-lock-fill',
                    'actions' => [
                        ['label' => 'Abrir auditoria', 'href' => site_url('audit'), 'class' => 'btn btn-primary', 'icon' => 'bi bi-search'],
                        ['label' => 'LGPD', 'href' => site_url('lgpd/consents'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi bi-file-earmark-lock'],
                    ],
                ],
                'kpis' => $this->kpis(),
                'sections' => [
                    'shortcuts' => [
                        'title' => 'Acessos rápidos',
                        'items' => [
                            ['href' => site_url('audit'), 'icon' => 'bi bi-shield-check', 'title' => 'Auditoria', 'description' => 'Inspecionar trilhas e eventos do sistema.'],
                            ['href' => site_url('lgpd/consents'), 'icon' => 'bi bi-file-earmark-lock2', 'title' => 'LGPD', 'description' => 'Acompanhar consentimentos e conformidade.'],
                            ['href' => site_url('profile'), 'icon' => 'bi bi-person-badge', 'title' => 'Meu perfil', 'description' => 'Revisar dados de acesso e conta.'],
                        ],
                    ],
                    'notifications' => [
                        'title' => 'Alertas recentes',
                        'items' => $this->notifications($userId),
                    ],
                ],
            ],
        ];
    }

    private function kpis(): array
    {
        $critical24h = $this->auditModel
            ->where('level', 'critical')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->countAllResults();

        $audit24h = $this->auditModel
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->countAllResults();

        $exports24h = $this->auditModel
            ->whereIn('action', [
                'EXPORT_CSV',
                'EXPORT_AFD',
                'REPORT_GENERATED',
                'REPORT_EXPORTED',
            ])
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->countAllResults();

        return [
            ['icon' => 'bi bi-shield-exclamation', 'iconColor' => 'danger', 'value' => (string) $critical24h, 'label' => 'Eventos críticos (24h)', 'classes' => 'grid-col-3'],
            ['icon' => 'bi bi-journal-text', 'iconColor' => 'primary', 'value' => (string) $audit24h, 'label' => 'Logs de auditoria (24h)', 'classes' => 'grid-col-3'],
            ['icon' => 'bi bi-download', 'iconColor' => 'warning', 'value' => (string) $exports24h, 'label' => 'Relatórios/exportações (24h)', 'classes' => 'grid-col-3'],
        ];
    }

    private function notifications(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->notificationModel
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->findAll();

        return array_map(static function ($row): array {
            $title = is_object($row) ? (string) ($row->title ?? 'Notificação') : (string) ($row['title'] ?? 'Notificação');
            $message = is_object($row) ? (string) ($row->message ?? '') : (string) ($row['message'] ?? '');
            $createdAt = is_object($row) ? (string) ($row->created_at ?? '') : (string) ($row['created_at'] ?? '');
            return ['title' => $title, 'message' => $message, 'createdAt' => $createdAt];
        }, $rows);
    }

    private function userValue(object|array|null $user, string $key, mixed $default = null): mixed
    {
        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user) && isset($user->{$key})) {
            return $user->{$key};
        }

        return $default;
    }
}

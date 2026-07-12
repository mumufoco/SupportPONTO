<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

final class FeatureAccessController extends BaseController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const DEFERRED_FEATURES = [
        'employees-wizard' => [
            'title' => 'Cadastro guiado de colaborador',
            'icon' => 'bi bi-diagram-3-fill',
            'reason' => 'O cadastro guiado ainda está em evolução e não deve ficar exposto como fluxo pronto em produção.',
            'safe_url' => 'employees/create',
            'safe_label' => 'Usar cadastro tradicional',
        ],
        'warnings-flow' => [
            'title' => 'Fluxo disciplinar',
            'icon' => 'bi bi-exclamation-triangle-fill',
            'reason' => 'O fluxo disciplinar visual ainda não representa o processo operacional completo e foi retirado da superfície de produção.',
            'safe_url' => 'warnings',
            'safe_label' => 'Abrir advertências',
        ],
        'analytics-punch-intelligence' => [
            'title' => 'Inteligência do ponto',
            'icon' => 'bi bi-cpu-fill',
            'reason' => 'Esta página exibia apenas insights estáticos e não deve ser interpretada como analytics real de produção.',
            'safe_url' => 'reports',
            'safe_label' => 'Abrir relatórios reais',
        ],
        'analytics-management' => [
            'title' => 'Analytics gerenciais',
            'icon' => 'bi bi-graph-up-arrow',
            'reason' => 'Os indicadores desta área ainda eram apresentados com dados mockados. O acesso direto foi despublicado até a implementação real.',
            'safe_url' => 'reports',
            'safe_label' => 'Abrir relatórios reais',
        ],
        'analytics-reports-advanced' => [
            'title' => 'Relatórios avançados',
            'icon' => 'bi bi-bar-chart-fill',
            'reason' => 'A página de relatórios avançados ainda estava em modo demonstrativo, sem fonte operacional consolidada.',
            'safe_url' => 'reports',
            'safe_label' => 'Abrir relatórios reais',
        ],
        'analytics-team-indicators' => [
            'title' => 'Indicadores por equipe',
            'icon' => 'bi bi-people-fill',
            'reason' => 'Os indicadores por equipe ainda eram renderizados com conteúdo estático e foram ocultados da produção.',
            'safe_url' => 'reports',
            'safe_label' => 'Abrir relatórios reais',
        ],
        'analytics-method-metrics' => [
            'title' => 'Métricas por método',
            'icon' => 'bi bi-fingerprint',
            'reason' => 'As métricas por método ainda não estavam ligadas ao pipeline real de dados e não devem ficar públicas como funcionalidade pronta.',
            'safe_url' => 'reports',
            'safe_label' => 'Abrir relatórios reais',
        ],
    ];

    public function deferred(string $featureKey): ResponseInterface
    {
        $feature = self::DEFERRED_FEATURES[$featureKey] ?? null;
        if ($feature === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setStatusCode(404)
            ->setBody(view('placeholders/feature_unavailable', [
                'title' => $feature['title'],
                'icon' => $feature['icon'],
                'message' => $feature['reason'],
                'safeUrl' => site_url((string) $feature['safe_url']),
                'safeLabel' => $feature['safe_label'],
            ]));
    }
}

<?php

return [
    'source' => [
        'dashboardAdmin' => 'Dashboard administrativo',
    ],
    'actions' => [
        'backToDashboard' => 'Voltar ao dashboard',
    ],
    'flows' => [
        'generic' => 'Fluxo operacional',
        'pendingQueue' => 'Fila completa de justificativas',
        'auditTrail' => 'Auditoria detalhada',
    ],
    'returns' => [
        'dashboard' => 'Retorno ao painel principal',
        'pendingSection' => 'Retorno à seção de justificativas pendentes',
        'recentSection' => 'Retorno à seção de atividades recentes',
        'alertsSection' => 'Retorno à seção de alertas do sistema',
    ],
    'badges' => [
        'origin' => 'Origem',
        'flow' => 'Fluxo',
        'currentScreen' => 'Tela atual',
        'suggestedReturn' => 'Retorno sugerido',
    ],
    'screens' => [
        'generic' => [
            'label' => 'Tela operacional',
            'title' => 'Fluxo iniciado pelo dashboard',
            'description' => 'Você abriu esta tela a partir do dashboard administrativo. Use o retorno rápido para voltar ao painel com contexto.',
        ],
        'justifications' => [
            'label' => 'Fila de justificativas',
            'title' => 'Você está na fila completa de justificativas',
            'description' => 'Esta listagem foi aberta a partir do dashboard administrativo. Depois da análise, use o retorno rápido para voltar ao painel no ponto de origem.',
        ],
        'audit' => [
            'label' => 'Auditoria detalhada',
            'title' => 'Você está na auditoria detalhada',
            'description' => 'Esta tela foi aberta a partir do dashboard administrativo para aprofundar a análise operacional. O retorno rápido leva você de volta ao painel com contexto.',
        ],
    ],
];

<?php

return [
    'title' => 'Dashboard do gestor',
    'subtitle' => 'Monitore equipe, aprovações e desempenho diário.',
    'icon' => 'bi bi-diagram-3-fill',
    'common' => [
        'missingText' => 'Não informado',
        'relativeMinutes' => 'Há {0} min',
        'relativeHours' => 'Há {0}h',
    ],
    'kpis' => [
        'totalEmployees' => ['label' => 'Funcionários na Equipe', 'indicator' => 'Ativos'],
        'attendanceRate' => ['label' => 'Taxa de Presença Hoje', 'indicator' => '+2%'],
        'pendingApprovals' => ['label' => 'Aprovações Pendentes', 'indicatorWarning' => 'Requer atenção', 'indicatorOk' => 'Tudo aprovado'],
        'absentToday' => ['label' => 'Ausências Hoje', 'indicator' => 'Sem justificativa'],
    ],
    'pending' => [
        'title' => 'Justificativas Pendentes',
        'action' => 'Ver Todas',
        'headers' => [
            'employee' => 'Funcionário',
            'type' => 'Tipo',
            'date' => 'Data',
            'submitted' => 'Enviado',
            'actions' => 'Ações',
        ],
        'emptyTitle' => 'Tudo aprovado',
        'emptyMessage' => 'Nenhuma justificativa pendente',
        'approve' => 'Aprovar',
        'reject' => 'Rejeitar',
    ],
    'activity' => [
        'title' => 'Atividade Recente da Equipe',
        'headers' => [
            'employee' => 'Funcionário',
            'action' => 'Ação',
            'time' => 'Hora',
            'status' => 'Status',
        ],
        'emptyTitle' => 'Nenhuma atividade recente',
        'emptyMessage' => 'Nenhuma atividade recente',
        'fallbackAction' => 'Ação',
        'statusApproved' => 'Aprovado',
        'statusActive' => 'Ativo',
    ],
    'quickActions' => [
        'title' => 'Ações Rápidas',
        'items' => [
            'createEmployee' => 'Cadastrar Funcionário',
            'reports' => 'Gerar Relatório',
            'schedules' => 'Escalas de Trabalho',
            'warnings' => 'Advertências',
        ],
    ],
    'alerts' => [
        'title' => 'Alertas',
        'fallbackMessage' => 'Alerta operacional',
        'pendingApprovalsMessage' => 'Você tem {0} justificativas pendentes de aprovação.',
    ],
    'punchActions' => [
        'entrada' => 'Registrou entrada',
        'saida' => 'Registrou saída',
        'intervalo_inicio' => 'Iniciou intervalo',
        'intervalo_fim' => 'Finalizou intervalo',
        'default' => 'Registrou ponto',
    ],
    'justificationTypes' => [
        'absence' => 'Falta',
        'late' => 'Atraso',
        'early_leave' => 'Saída Antecipada',
        'forgot_punch' => 'Esqueceu de Bater',
        'other' => 'Outro',
    ],
];

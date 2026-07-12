<?php

return [
    'title' => 'Meu painel',
    'subtitle' => 'Acompanhe sua jornada, registre o ponto e acesse suas ações mais importantes.',
    'icon' => 'bi bi-person-workspace',
    'common' => [
        'defaultUser' => 'Funcionário',
    ],
    'actions' => [
        'punch' => 'Bater ponto',
        'history' => 'Espelho',
        'justifications' => 'Justificativas',
    ],
    'hero' => [
        'greeting' => 'Olá, {0}.',
        'clockedIn' => 'Trabalhando no momento',
        'clockedOut' => 'Aguardando novo registro',
        'cta' => 'Registrar ponto agora',
        'statusLabel' => 'Status atual:',
    ],
    'kpis' => [
        'hoursWorked' => ['label' => 'Horas trabalhadas', 'indicator' => 'Mês atual'],
        'balance' => ['label' => 'Banco de horas', 'positive' => 'Positivo', 'negative' => 'Negativo'],
        'attendance' => ['label' => 'Presença', 'indicator' => 'Assiduidade'],
        'pending' => ['label' => 'Pendências', 'indicator' => 'Acompanhe solicitações'],
    ],
    'shortcuts' => [
        'sectionTitle' => 'Atalhos rápidos',
        'punch' => ['title' => 'Registrar ponto', 'description' => 'Entrada, saída e intervalos em uma tela única.'],
        'history' => ['title' => 'Espelho de ponto', 'description' => 'Consulte marcações e acompanhe seu histórico.'],
        'justification' => ['title' => 'Nova justificativa', 'description' => 'Solicite análise de atrasos, ausências ou ajustes.'],
        'profile' => ['title' => 'Meu perfil', 'description' => 'Atualize dados pessoais e configurações de conta.'],
    ],
];

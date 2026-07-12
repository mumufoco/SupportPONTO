<?php

namespace App\Services\Compliance;

class AuditAdvancedService
{
    public function getEvents(): array
    {
        return [
            [
                'title' => 'Tentativa de login inválida',
                'desc' => 'Múltiplas tentativas detectadas em usuário sensível.',
                'meta' => 'Segurança • Alta criticidade • Hoje 08:10',
            ],
            [
                'title' => 'Alteração de perfil de acesso',
                'desc' => 'Usuário promovido para perfil gestor.',
                'meta' => 'Admin • Média criticidade • Hoje 09:25',
            ],
            [
                'title' => 'Atualização de certificado',
                'desc' => 'Certificado digital substituído no ambiente.',
                'meta' => 'Admin • Alta criticidade • Hoje 11:00',
            ],
        ];
    }
}

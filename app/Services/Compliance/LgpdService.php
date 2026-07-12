<?php

namespace App\Services\Compliance;

class LgpdService
{
    public function getCards(): array
    {
        return [
            ['value' => '84%', 'label' => 'Consentimentos registrados'],
            ['value' => '4', 'label' => 'Biometrias pendentes de aceite'],
            ['value' => '2', 'label' => 'Solicitações sensíveis em análise'],
            ['value' => 'Ok', 'label' => 'Fluxo de aceite operacional'],
        ];
    }

    public function getGuidelines(): array
    {
        return [
            ['title' => 'Consentimento', 'desc' => 'Registrar aceite antes do uso de biometria facial e digital.'],
            ['title' => 'Rastreabilidade', 'desc' => 'Manter logs de alterações e operações sensíveis relacionadas a dados pessoais.'],
            ['title' => 'Revogação', 'desc' => 'Preparar fluxo administrativo para atualização, revisão e eventual revogação quando aplicável.'],
        ];
    }
}

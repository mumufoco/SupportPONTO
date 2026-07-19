<?php

declare(strict_types=1);

namespace App\Services\LGPD;

/**
 * Catalogo unico dos tipos de consentimento LGPD exigidos de todo colaborador
 * (qualquer papel) para uso do sistema -- fonte compartilhada entre o fluxo
 * completo de aceite (Auth\ConsentGateController) e o lembrete flutuante
 * exibido no dashboard enquanto algum ficar pendente.
 *
 * Biometria facial/digital NAO entram aqui -- tem fluxo proprio, atrelado ao
 * cadastro biometrico (Biometric\BiometricConsentController).
 */
final class ConsentGateCatalog
{
    public const TYPES = [
        'data_processing' => [
            'label'       => 'Processamento de Dados Pessoais',
            'icon'        => 'bi bi-person-lines-fill',
            'description' => 'Autoriza o tratamento dos seus dados pessoais para fins de gestão de ponto, folha de pagamento e obrigações trabalhistas.',
            'legal_basis' => 'LGPD Art. 7º, V – Execução de contrato',
            'required'    => true,
        ],
        'data_sharing' => [
            'label'       => 'Compartilhamento de Dados',
            'icon'        => 'bi bi-share-fill',
            'description' => 'Autoriza o compartilhamento de dados com prestadores de benefícios, parceiros de segurança do trabalho e órgãos regulatórios conforme exigido por lei.',
            'legal_basis' => 'LGPD Art. 7º, V – Execução de contrato',
            'required'    => true,
        ],
        'geolocation' => [
            'label'       => 'Geolocalização',
            'icon'        => 'bi bi-geo-alt-fill',
            'description' => 'Autoriza o uso da sua localização geográfica para registro de ponto em campo, controle de limites virtuais e validação de presença.',
            'legal_basis' => 'LGPD Art. 7º, I – Consentimento',
            'required'    => true,
        ],
        'marketing' => [
            'label'       => 'Comunicações de Marketing',
            'icon'        => 'bi bi-megaphone-fill',
            'description' => 'Autoriza o envio de comunicados sobre novidades, atualizações e informações institucionais da empresa por e-mail ou mensagem.',
            'legal_basis' => 'LGPD Art. 7º, I – Consentimento',
            'required'    => true,
        ],
    ];
}

<?php

namespace App\Services\LGPD\Concerns;

trait ConsentServiceNotificationTrait
{
    protected function notifyConsentGranted(object $employee, string $consentType, object $consent): void
    {
        $email = \Config\Services::email();

        $consentLabels = [
            'biometric_face' => 'Biometria Facial',
            'biometric_fingerprint' => 'Biometria Digital',
            'geolocation' => 'Geolocalização',
            'data_processing' => 'Processamento de Dados',
            'marketing' => 'Marketing',
            'data_sharing' => 'Compartilhamento de Dados',
        ];

        $label = $consentLabels[$consentType] ?? $consentType;

        $email->setTo(env('DPO_EMAIL', 'dpo@empresa.com'));
        $email->setSubject('[LGPD] Novo Consentimento Concedido');
        $email->setMessage("
            <h3>Novo Consentimento LGPD</h3>
            <p><strong>Colaborador:</strong> {$employee->name} (ID: {$employee->id})</p>
            <p><strong>Tipo:</strong> {$label}</p>
            <p><strong>Data:</strong> {$consent->granted_at}</p>
            <p><strong>IP:</strong> {$consent->ip_address}</p>
            <p><strong>Versão do Termo:</strong> {$consent->version}</p>
        ");

        try {
            $email->send();
        } catch (\Exception $e) {
            log_message('error', 'Failed to send consent granted notification: ' . $e->getMessage());
        }
    }
    protected function notifyConsentRevoked(
        object $employee,
        string $consentType,
        ?string $reason,
        int $deletedRecords
    ): void {
        $email = \Config\Services::email();

        $consentLabels = [
            'biometric_face' => 'Biometria Facial',
            'biometric_fingerprint' => 'Biometria Digital',
            'geolocation' => 'Geolocalização',
            'data_processing' => 'Processamento de Dados',
            'marketing' => 'Marketing',
            'data_sharing' => 'Compartilhamento de Dados',
        ];

        $label = $consentLabels[$consentType] ?? $consentType;

        $email->setTo(env('DPO_EMAIL', 'dpo@empresa.com'));
        $email->setSubject('[LGPD] Consentimento Revogado - Ação Necessária');
        $email->setMessage("
            <h3>Consentimento LGPD Revogado</h3>
            <p><strong>Colaborador:</strong> {$employee->name} (ID: {$employee->id})</p>
            <p><strong>Tipo:</strong> {$label}</p>
            <p><strong>Data da Revogação:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>Motivo:</strong> " . ($reason ?? 'Não informado') . "</p>
            <p><strong>Registros Biométricos Deletados:</strong> {$deletedRecords}</p>
            <hr>
            <p><em>Esta é uma notificação automática para conformidade LGPD.</em></p>
        ");

        try {
            $email->send();
        } catch (\Exception $e) {
            log_message('error', 'Failed to send consent revoked notification: ' . $e->getMessage());
        }
    }

}

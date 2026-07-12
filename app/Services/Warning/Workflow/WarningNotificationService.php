<?php

namespace App\Services\Warning\Workflow;

use App\Models\SettingModel;
use App\Services\NotificationService;

class WarningNotificationService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function notifyTargetEmployee(object $targetEmployee, string $warningType, int $warningId, int $employeeId, ?object $issuer = null, ?object $warning = null): void
    {
        helper('operational_link');

        $this->notificationService->create(
            $employeeId,
            'Nova advertência recebida',
            "Foi registrada uma advertência ({$warningType}). Acesse o documento para ciência e assinatura.",
            'danger',
            sp_warning_sign_path($warningId)
        );

        $email = \Config\Services::email();
        $email->setTo($targetEmployee->email);
        $email->setSubject('Advertência disciplinar - ciência e assinatura');
        $email->setMessage(view('emails/warning_notification', [
            'employee' => $targetEmployee,
            'warning_type' => $warningType,
            'warning_number' => $warningId,
            'occurrence_date' => $warning->occurrence_date ?? null,
            'issuer_name' => $issuer->name ?? null,
            'reason' => $warning->reason ?? null,
            'link' => sp_warning_sign_url($warningId),
            'sign_url' => sp_warning_sign_url($warningId),
            'show_url' => sp_warning_show_url($warningId),
            'employee_name' => $targetEmployee->name ?? null,
            'company_name' => $this->companyName(),
            'support_email' => $this->supportEmail(),
        ]));
        $email->send();
    }

    public function notifyIssuerWarningSigned(int $issuerId, string $actorName, int $warningId): void
    {
        $this->notificationService->create(
            $issuerId,
            'Advertência assinada',
            "{$actorName} registrou ciência e assinatura da advertência emitida.",
            'success',
            sp_warning_show_path($warningId)
        );
    }

    public function notifyAdminsRefusal(array $admins, object $targetEmployee, int $warningId): void
    {
        foreach ($admins as $admin) {
            $this->notificationService->create(
                $admin->id,
                'Advertência recusada',
                "O colaborador {$targetEmployee->name} recusou a assinatura. O registro foi encaminhado para testemunha.",
                'warning',
                sp_warning_show_path($warningId)
            );
        }
    }

    private function companyName(): string
    {
        return (string) (new SettingModel())->get('company_name', 'Support Solo Sondagens');
    }

    private function supportEmail(): string
    {
        return (string) (new SettingModel())->get('contact_email', 'contato@supportsondagens.com.br');
    }
}

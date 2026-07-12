<?php

namespace App\Services\SupportCheck;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Models\ConsentTermModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;

/**
 * Envia ao SupportCHECK os consentimentos/termos ja concedidos pelo
 * colaborador no SupportPONTO, para que sigam o fluxo de assinatura e
 * armazenamento la (pagina "Integracao Termos").
 */
class SupportCheckTermService
{
    public function __construct(
        private readonly SupportCheckApiClient $client     = new SupportCheckApiClient(),
        private readonly UserConsentModel $consentModel    = new UserConsentModel(),
        private readonly EmployeeModel $employeeModel       = new EmployeeModel(),
        private readonly ConsentTermModel $termModel        = new ConsentTermModel(),
    ) {
    }

    /**
     * Envia os consentimentos concedidos e ainda nao sincronizados.
     * Se $employeeId for informado, envia apenas os desse colaborador.
     *
     * @return array{sent: int, failed: int, errors: array<string>}
     */
    public function sendPending(?int $employeeId = null): array
    {
        $consents = $this->consentModel
            ->where('granted', true)
            ->where('synced_to_supportcheck_at', null)
            ->when($employeeId !== null, fn ($builder) => $builder->where('employee_id', $employeeId))
            ->findAll();

        $sent   = 0;
        $failed = 0;
        $errors = [];

        foreach ($consents as $consent) {
            try {
                $this->sendOne($consent);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Consentimento #' . $consent->id . ' (employee #' . $consent->employee_id . '): ' . $e->getMessage();
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * @param object $consent Linha de user_consents
     */
    private function sendOne(object $consent): void
    {
        $employee = $this->employeeModel->find($consent->employee_id);
        $termTemplate = $this->termModel->getActiveTerm($consent->consent_type);

        $payload = [
            'supportponto_term_id' => 'consent-' . $consent->id,
            'supportponto_employee_id' => (string) $consent->employee_id,
            'employee_name' => $consent->employee_name_snapshot ?? $employee->name ?? null,
            'employee_cpf' => $consent->employee_cpf_snapshot ?? $employee->cpf ?? null,
            'employee_email' => $employee->email ?? null,
            'term_type' => $consent->consent_type,
            'term_title' => $termTemplate->title ?? ('Termo de consentimento - ' . $consent->consent_type),
            'term_version' => $consent->version,
            'generated_at' => $consent->granted_at,
            'requires_signature' => true,
            'source_file_content' => $consent->consent_text,
            // Nao enviamos evidence_hash como source_file_hash: o SupportCHECK valida
            // esse campo como sha256(source_file_content) para garantir integridade de
            // transferencia, mas evidence_hash do PONTO e um hash de evidencia/auditoria
            // com proposito diferente e nao corresponde a esse calculo -- enviar geraria
            // falha de validacao (hash nao confere) no SupportCHECK.
            'metadata' => [
                'required_signers' => ['colaborador'],
                'origin' => 'user_consents',
                'legal_basis' => $consent->legal_basis,
                'evidence_hash' => $consent->evidence_hash ?? null,
            ],
        ];

        $this->client->sendTermCreated($payload);

        $this->consentModel->update($consent->id, [
            'synced_to_supportcheck_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

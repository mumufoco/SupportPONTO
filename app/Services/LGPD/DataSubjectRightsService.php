<?php

namespace App\Services\LGPD;

use App\Models\EmployeeModel;

class DataSubjectRightsService
{
    private EmployeeModel $employeeModel;
    private PrivacyAuditLoggerService $auditLogger;
    private DataAnonymizationService $anonymizationService;
    private DataRetentionPolicyService $retentionService;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->auditLogger = new PrivacyAuditLoggerService();
        $this->anonymizationService = new DataAnonymizationService();
        $this->retentionService = new DataRetentionPolicyService();
    }

    /**
     * @return array<string,mixed>
     */
    public function registerSubjectRequest(int $employeeId, string $type, ?string $reason = null, ?int $actorId = null): array
    {
        $allowed = ['access', 'export', 'correction', 'anonymization', 'deactivation', 'biometric_purge'];
        if (!in_array($type, $allowed, true)) {
            return ['success' => false, 'message' => 'Tipo de solicitação LGPD inválido.'];
        }

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Titular não encontrado.'];
        }

        $db = \Config\Database::connect();
        $requestId = 'LGPD-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $payload = [
            'request_id' => $requestId,
            'employee_id' => $employeeId,
            'requested_by' => $actorId ?: $employeeId,
            'request_type' => $type,
            'status' => 'pending',
            'reason' => $reason,
            'due_at' => date('Y-m-d H:i:s', strtotime('+' . (int) env('LGPD_REQUEST_SLA_DAYS', 15) . ' days')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($db->tableExists('lgpd_subject_requests')) {
            $db->table('lgpd_subject_requests')->insert($payload);
        }

        $this->auditLogger->log($actorId ?: $employeeId, 'LGPD_SUBJECT_REQUEST_CREATED', 'lgpd_subject_requests', $employeeId, null, [
            'request_id' => $requestId,
            'request_type' => $type,
            'due_at' => $payload['due_at'],
        ], 'Solicitação de direito do titular registrada.', 'info');

        $this->notifyDpoOfRequest($employee, $requestId, $type, $reason, $payload['due_at']);

        return ['success' => true, 'message' => 'Solicitação registrada para análise do DPO.', 'request_id' => $requestId, 'due_at' => $payload['due_at']];
    }

    /**
     * Desativação preserva registros obrigatórios e bloqueia uso operacional.
     * Anonimização efetiva deve respeitar retenção/legal hold.
     *
     * @return array<string,mixed>
     */
    public function deactivateEmployeeForPrivacy(int $employeeId, ?int $actorId, string $reason): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Titular não encontrado.'];
        }

        $retentionUntil = $this->retentionService->calculateRetentionUntil('employee_master_data', $employee->demission_date ?? null);
        $updated = $this->employeeModel->update($employeeId, [
            'active' => false,
            'remember_token' => null,
            'remember_token_expires' => null,
        ]);

        $this->auditLogger->log($actorId, 'LGPD_PRIVACY_DEACTIVATION', 'employees', $employeeId, [
            'active' => $employee->active ?? null,
        ], [
            'active' => false,
            'reason' => $reason,
            'retention_until' => $retentionUntil,
        ], 'Titular desativado por fluxo LGPD com preservação de registros legais.', 'warning');

        return [
            'success' => (bool) $updated,
            'message' => $updated ? 'Titular desativado e retenção registrada em auditoria.' : 'Falha ao desativar titular.',
            'retention_until' => $retentionUntil,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function anonymizeEmployeeWhenAllowed(int $employeeId, ?int $actorId, string $reason): array
    {
        $result = $this->anonymizationService->anonymizeEmployee($employeeId, (string) ($actorId ?? 'DPO'), $reason);
        $this->auditLogger->log($actorId, 'LGPD_PRIVACY_ANONYMIZATION', 'employees', $employeeId, null, [
            'reason' => $reason,
            'result' => [
                'success' => $result['success'] ?? false,
                'anonymized_count' => $result['anonymized_count'] ?? 0,
            ],
        ], 'Anonimização/desidentificação executada por fluxo LGPD.', 'warning');

        return $result;
    }
    private function notifyDpoOfRequest(object $employee, string $requestId, string $type, ?string $reason, string $dueAt): void
    {
        try {
            $dpoEmail = env('DPO_EMAIL');
            if (! $dpoEmail) {
                $row = \Config\Database::connect()->table('settings')
                    ->where('key', 'lgpd_dpo_email')->get()->getRow();
                $dpoEmail = ($row && ! empty($row->value)) ? (string) $row->value : 'dpo@empresa.com';
            }

            $typeLabels = [
                'access'          => 'Acesso aos Dados (Art. 18, I)',
                'export'          => 'Portabilidade (Art. 19)',
                'correction'      => 'Correcao de Dados (Art. 18, III)',
                'anonymization'   => 'Anonimizacao (Art. 18, IV)',
                'deactivation'    => 'Desativacao / Eliminacao (Art. 18, VI)',
                'biometric_purge' => 'Expurgo Biometrico (Art. 18)',
            ];

            $label = $typeLabels[$type] ?? $type;
            $employeeName = htmlspecialchars((string) ($employee->name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $reasonEsc    = htmlspecialchars((string) ($reason ?? 'Nao informado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $email = \Config\Services::email();
            $email->setTo($dpoEmail);
            $email->setSubject('[LGPD] Nova Solicitacao de Titular — ' . strip_tags($label));
            $email->setMessage(
                '<h3>Nova Solicitacao de Direito do Titular — LGPD Art. 18</h3>' .
                '<table cellpadding="6" style="border-collapse:collapse;">' .
                '<tr><td><strong>Protocolo:</strong></td><td>' . esc($requestId) . '</td></tr>' .
                '<tr><td><strong>Titular:</strong></td><td>' . $employeeName . ' (ID: ' . (int) $employee->id . ')</td></tr>' .
                '<tr><td><strong>Tipo:</strong></td><td>' . esc($label) . '</td></tr>' .
                '<tr><td><strong>Motivo:</strong></td><td>' . $reasonEsc . '</td></tr>' .
                '<tr><td><strong>Prazo legal:</strong></td><td>' . esc($dueAt) . '</td></tr>' .
                '</table>' .
                '<hr><p><em>Acesse o painel LGPD (lgpd/admin) para analisar e resolver esta solicitacao.</em></p>'
            );
            $email->send();
        } catch (\Throwable $e) {
            log_message('error', 'LGPD: falha ao notificar DPO sobre solicitacao {id}: {err}', [
                'id' => $requestId, 'err' => $e->getMessage(),
            ]);
        }
    }

}

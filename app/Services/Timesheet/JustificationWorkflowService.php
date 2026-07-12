<?php

namespace App\Services\Timesheet;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Services\NotificationService;
use App\Enums\Role;

class JustificationWorkflowService
{
    public function __construct(
        private readonly JustificationModel $justificationModel = new JustificationModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly NotificationService $notificationService = new NotificationService(),
        private readonly AuditModel $auditModel = new AuditModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function create(array $actor, array $payload, array $attachmentFiles): array
    {
        helper(['file_upload', 'observability']);
        $justificationDate = (string) ($payload['justification_date'] ?? '');
        if ($justificationDate !== '' && strtotime($justificationDate) > strtotime(date('Y-m-d'))) {
            supportponto_log_event('warning', 'justification', 'future_date_blocked', [
                'employee_id' => $actor['id'] ?? null,
                'justification_date' => $justificationDate,
            ]);

            return ['success' => false, 'error' => 'Não é permitido justificar datas futuras.'];
        }

        $attachments = $this->processAttachments($attachmentFiles, (int) $actor['id']);
        if (!empty($attachments['errors'])) {
            supportponto_log_event('warning', 'justification', 'attachment_validation_failed', [
                'employee_id' => $actor['id'] ?? null,
                'error_count' => count($attachments['errors']),
            ]);

            return ['success' => false, 'error' => implode('<br>', $attachments['errors'])];
        }

        $status = $this->canManageJustifications($actor) ? 'aprovado' : 'pendente';

        $data = [
            'employee_id' => $actor['id'],
            'justification_date' => $justificationDate,
            'justification_type' => $payload['justification_type'] ?? null,
            'category' => $payload['category'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'attachments' => json_encode($attachments['paths'] ?? []),
            'status' => $status,
            'approved_by' => $status === 'aprovado' ? $actor['id'] : null,
            'approved_at' => $status === 'aprovado' ? date('Y-m-d H:i:s') : null,
            'submitted_by' => $actor['id'],
        ];

        $justificationId = $this->justificationModel->insert($data);
        if (!$justificationId) {
            $this->cleanupAttachments($attachments['paths']);
            supportponto_log_event('error', 'justification', 'create_failed', [
                'employee_id' => $actor['id'] ?? null,
                'status' => $status,
                'attachment_count' => count($attachments['paths']),
            ]);
            return ['success' => false, 'error' => 'Erro ao criar justificativa.'];
        }

        $this->auditModel->log(
            (int) $actor['id'],
            'JUSTIFICATION_CREATED',
            'justifications',
            (int) $justificationId,
            null,
            $data,
            "Justificativa criada para {$justificationDate} (tipo: {$data['justification_type']})",
            'info'
        );

        supportponto_log_event('info', 'justification', 'created', [
            'employee_id' => $actor['id'] ?? null,
            'justification_id' => (int) $justificationId,
            'status' => $status,
            'attachment_count' => count($attachments['paths']),
        ]);

        if ($status === 'pendente') {
            $this->notifyManagers($actor, (int) $justificationId);
        }

        return [
            'success' => true,
            'message' => $status === 'aprovado'
                ? 'Justificativa criada e aprovada automaticamente.'
                : 'Justificativa enviada com sucesso! Aguarde aprovação.',
        ];
    }

    public function approve(int $id, array $actor, ?string $notes): array
    {
        $justification = $this->justificationModel->find($id);
        if (!$justification || !is_object($justification)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Justificativa não encontrada.'];
        }

        $this->justificationModel->update($id, [
            'status' => 'aprovado',
            'reviewed_by' => $actor['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ]);

        $displayDate = $justification->date ?? $justification->justification_date ?? '';
        $this->notificationService->create(
            (int) $justification->employee_id,
            'Justificativa Aprovada',
            'Sua justificativa de ' . format_date_br((string) $displayDate) . ' foi aprovada.',
            'success',
            '/justifications/' . $id
        );

        $this->auditModel->log((int) ($actor['id'] ?? 0), 'JUSTIFICATION_APPROVED', 'justifications', $id, ['status' => $justification->status ?? 'pendente'], ['status' => 'aprovado'], 'Justificativa aprovada', 'info');
        supportponto_log_event('info', 'justification', 'approved', [
            'employee_id' => $actor['id'] ?? null,
            'justification_id' => $id,
        ]);

        return ['success' => true, 'message' => 'Justificativa aprovada com sucesso.'];
    }

    public function reject(int $id, array $actor, ?string $notes): array
    {
        if (empty($notes)) {
            return ['success' => false, 'validation_error' => true, 'error' => 'Informe o motivo da rejeição.'];
        }

        $justification = $this->justificationModel->find($id);
        if (!$justification || !is_object($justification)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Justificativa não encontrada.'];
        }

        $this->justificationModel->update($id, [
            'status' => 'rejeitado',
            'reviewed_by' => $actor['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ]);

        $displayDate = $justification->date ?? $justification->justification_date ?? '';
        $this->notificationService->create(
            (int) $justification->employee_id,
            'Justificativa Rejeitada',
            'Sua justificativa de ' . format_date_br((string) $displayDate) . ' foi rejeitada. Motivo: ' . $notes,
            'danger',
            '/justifications/' . $id
        );

        $this->auditModel->log((int) ($actor['id'] ?? 0), 'JUSTIFICATION_REJECTED', 'justifications', $id, ['status' => $justification->status ?? 'pendente'], ['status' => 'rejeitado'], 'Justificativa rejeitada', 'warning');
        supportponto_log_event('warning', 'justification', 'rejected', [
            'employee_id' => $actor['id'] ?? null,
            'justification_id' => $id,
        ]);

        return ['success' => true, 'message' => 'Justificativa rejeitada.'];
    }

    public function delete(int $id, array $actor): array
    {
        $justification = $this->justificationModel->find($id);
        if (!$justification || !is_object($justification)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Justificativa não encontrada.'];
        }

        if ($actor['role'] !== 'admin' && ((int) $justification->employee_id !== (int) $actor['id'] || $justification->status !== 'pendente')) {
            return ['success' => false, 'error' => 'Você só pode excluir justificativas pendentes.'];
        }

        // Soft delete preserva anexos para trilha de auditoria e eventual restauração.
        $this->justificationModel->delete($id);
        $this->auditModel->log((int) ($actor['id'] ?? 0), 'JUSTIFICATION_DELETED', 'justifications', $id, ['status' => $justification->status ?? null], null, 'Justificativa excluída', 'warning');
        supportponto_log_event('warning', 'justification', 'deleted', [
            'employee_id' => $actor['id'] ?? null,
            'justification_id' => $id,
        ]);

        return ['success' => true, 'message' => 'Justificativa excluída com sucesso.'];
    }

    private function processAttachments(array $attachmentFiles, int $employeeId): array
    {
        helper(['file_upload', 'observability']);

        $attachmentPaths = [];
        $errors = [];
        $processed = 0;

        foreach ($attachmentFiles as $file) {
            if (!$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $processed++;
            if ($processed > 3) {
                $errors[] = 'Máximo de 3 arquivos permitidos.';
                break;
            }

            $upload = upload_justification_attachment($file, $employeeId);
            if (($upload['success'] ?? false) !== true) {
                $errors[] = $upload['message'] ?? "Erro ao processar '{$file->getClientName()}'.";
                continue;
            }

            $attachmentPaths[] = $upload['file_path'];
        }

        if (!empty($errors)) {
            $this->cleanupAttachments($attachmentPaths);
            supportponto_log_event('warning', 'upload', 'justification.batch_failed', [
                'employee_id' => $employeeId,
                'uploaded_count' => count($attachmentPaths),
                'error_count' => count($errors),
            ]);

            return ['paths' => [], 'errors' => $errors];
        }

        return ['paths' => $attachmentPaths, 'errors' => []];
    }

    private function cleanupAttachments(array $paths): void
    {
        helper('observability');

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $fullPath = WRITEPATH . $path;
            if (!file_exists($fullPath)) {
                continue;
            }

            if (!@unlink($fullPath)) {
                supportponto_log_event('warning', 'upload', 'justification.cleanup_failed', [
                    'path' => $path,
                ]);
            }
        }
    }


    private function canManageJustifications(array $actor): bool
    {
        try {
            return Role::normalize((string) ($actor['role'] ?? Role::Funcionario->value))->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }

    private function notifyManagers(array $actor, int $justificationId): void
    {
        $managers = $this->employeeModel
            ->whereIn('role', [Role::Admin->value, Role::Gestor->value, Role::RH->value])
            ->where('active', true)
            ->findAll();

        foreach ($managers as $manager) {
            if (Role::normalize((string) $manager->role)->value === Role::Gestor->value && $manager->department !== ($actor['department'] ?? null)) {
                continue;
            }

            $this->notificationService->create(
                $manager->id,
                'Nova Justificativa',
                $actor['name'] . ' enviou uma justificativa para aprovação.',
                'warning',
                '/justifications/' . $justificationId
            );
        }
    }
}

<?php

namespace App\Services\Employees\Management;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\PendingPunchModel;
use App\Services\Auth\SessionSecurityService;

class EmployeeStatusService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly NotificationModel $notificationModel,
        private readonly SessionSecurityService $sessionSecurityService = new SessionSecurityService(),
        private readonly PendingPunchModel $pendingPunchModel = new PendingPunchModel(),
        private readonly JustificationModel $justificationModel = new JustificationModel(),
    ) {
    }

    public function deleteEmployee(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        if (!$this->employeeModel->delete($id)) {
            return ['success' => false, 'error' => 'Erro ao excluir funcionário.', 'status' => 500];
        }

        // ALTO-02 (auditoria): sem isto, um funcionário excluído continuava com acesso
        // pleno pela sessão já aberta até ela expirar naturalmente.
        $this->sessionSecurityService->revokeAllSessionsForUser($id, 'Funcionário excluído');

        // MED-10 (auditoria): sem isto, pendências de ponto e justificativas
        // continuavam nas filas de aprovação de gestores mesmo após o desligamento, e
        // podiam ser aprovadas para alguém que já não estava mais na folha.
        $this->cancelOpenPendencies($id, 'Funcionário excluído do sistema.');

        return ['success' => true, 'employee' => $employee];
    }

    public function setEmployeeActive(int $id, bool $active): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->update($id, ['active' => $active]);
        $this->dispatchSupportCheckSync($id);

        // ALTO-02 (auditoria): desativar um funcionário (ex.: desligamento) não
        // revogava a sessão já aberta — quem estivesse logado continuava acessando o
        // sistema normalmente até a sessão expirar naturalmente (até ~2h, conforme
        // session.expiration), mesmo após uma ação administrativa explícita de bloqueio.
        if (!$active) {
            $this->sessionSecurityService->revokeAllSessionsForUser($id, 'Funcionário desativado');

            // MED-10 (auditoria): ver nota em deleteEmployee() acima.
            $this->cancelOpenPendencies($id, 'Funcionário desativado/desligado.');
        }

        return ['success' => true, 'employee' => $employee, 'active' => $active];
    }

    /**
     * Ver EmployeeRegistrationService::dispatchSupportCheckSync() -- mesmo
     * padrao (job assincrono, nunca bloqueia a acao administrativa em si).
     */
    private function dispatchSupportCheckSync(int $employeeId): void
    {
        try {
            (new \App\Services\Queue\AsyncJobService())->dispatchSupportCheckEmployeeSync($employeeId);
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao enfileirar sincronizacao SupportCHECK para funcionario #{id}: {msg}', ['id' => $employeeId, 'msg' => $e->getMessage()]);
        }
    }

    private function cancelOpenPendencies(int $employeeId, string $reason): void
    {
        $this->pendingPunchModel->cancelAllForEmployee($employeeId, $reason);
        $this->justificationModel->cancelAllPendingForEmployee($employeeId, $reason);
    }

    public function getPendingRegistrations(): array
    {
        // Truly pending: inactive and no demission_date = new registration awaiting approval
        return $this->employeeModel
            ->where('active', false)
            ->where('demission_date IS NULL', null, false)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getTerminatedEmployees(): array
    {
        // Terminated: inactive and demission_date is set (admin deactivated)
        return $this->employeeModel
            ->where('active', false)
            ->where('demission_date IS NOT NULL', null, false)
            ->orderBy('demission_date', 'DESC')
            ->findAll();
    }

    public function approveRegistration(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->update($id, ['active' => true]);
        $this->dispatchSupportCheckSync($id);

        $employeeId = is_array($employee) ? (int) ($employee['id'] ?? 0) : (int) ($employee->id ?? 0);

        $this->notificationModel->insert([
            'employee_id' => $employeeId,
            'title' => 'Cadastro aprovado',
            'message' => 'Seu cadastro foi aprovado. Você já pode acessar o sistema.',
            'type' => 'employee_registration',
            'read' => false,
        ]);

        return ['success' => true, 'employee' => $employee];
    }

    public function rejectRegistration(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->delete($id);

        return ['success' => true, 'employee' => $employee];
    }
}

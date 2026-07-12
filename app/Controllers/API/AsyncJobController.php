<?php

namespace App\Controllers\API;

use App\Models\EmployeeModel;
use App\Services\Queue\AsyncJobService;

class AsyncJobController extends BaseApiController
{
    protected AsyncJobService $asyncJobService;
    protected EmployeeModel $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->asyncJobService = new AsyncJobService();
        $this->employeeModel = new EmployeeModel();
    }

    public function status(string $jobId)
    {
        helper('operational_link');

        $employee = $this->getAuthenticatedEmployee();
        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $job = $this->asyncJobService->getJobStatus($jobId);
        if (! $job) {
            return $this->failStandard('job_not_found', 'Job não encontrado.', 404);
        }

        if (! $this->canAccessManagedJob($employee, $job)) {
            return $this->failStandard('forbidden', 'Sem permissão para acessar este job. Jobs operacionais ficam disponíveis para admin, gestor ou RH; o DPO mantém foco em auditoria e compliance.', 403);
        }

        return $this->respondStandard(
            $this->asyncJobService->presentJobStatus($job, sp_api_async_job_download_url($jobId)),
            'Status do job carregado com sucesso.',
            200,
            'async_job_status_success'
        );
    }

    public function download(string $jobId)
    {
        helper('file_upload');

        $employee = $this->getAuthenticatedEmployee();
        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $job = $this->asyncJobService->getJobStatus($jobId);
        if (! $job) {
            return $this->failStandard('job_not_found', 'Job não encontrado.', 404);
        }

        if (! $this->canAccessManagedJob($employee, $job)) {
            return $this->failStandard('forbidden', 'Sem permissão para acessar este job. Jobs operacionais ficam disponíveis para admin, gestor ou RH; o DPO mantém foco em auditoria e compliance.', 403);
        }

        if (($job['status'] ?? null) !== AsyncJobService::STATUS_COMPLETED) {
            return $this->failStandard('job_not_ready', 'Arquivo ainda não está disponível.', 409);
        }

        $safePath = supportponto_safe_download_path((string) ($job['result_file_path'] ?? ''));
        if ($safePath === null) {
            return $this->failStandard('job_file_not_found', 'Arquivo do job não encontrado ou indisponível para download.', 404);
        }

        return $this->response->download($safePath, null)->setFileName(basename($safePath));
    }

    private function canAccessManagedJob(object $employee, array $job): bool
    {
        $ownerId = (int) ($job['employee_id'] ?? 0);

        if ($ownerId > 0 && $ownerId === (int) $employee->id) {
            return true;
        }

        $ownerContext = $this->resolveJobOwnerContext($ownerId);
        if ($ownerContext === null) {
            return false;
        }

        return $this->authorizationService->canAccessAsyncJob($employee, $ownerContext);
    }

    private function resolveJobOwnerContext(int $ownerId): ?array
    {
        if ($ownerId <= 0) {
            return null;
        }

        $owner = $this->employeeModel->find($ownerId);
        if (! $owner) {
            return null;
        }

        return [
            'owner_id' => (int) ($owner->id ?? 0),
            'owner_department' => (string) ($owner->department ?? ''),
        ];
    }
}

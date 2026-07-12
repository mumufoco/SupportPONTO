<?php

namespace App\Controllers;

use App\Models\EmployeeModel;
use App\Services\Queue\AsyncJobService;
use CodeIgniter\HTTP\ResponseInterface;

class AsyncJobController extends BaseController
{
    protected AsyncJobService $asyncJobService;
    protected EmployeeModel $employeeModel;

    public function __construct()
    {
        $this->asyncJobService = new AsyncJobService();
        $this->employeeModel = new EmployeeModel();
    }

    public function status(string $jobId): ResponseInterface
    {
        $this->requireAuth();

        $job = $this->asyncJobService->getJobStatus($jobId);
        if (! $job) {
            return $this->response->setJSON(['success' => false, 'message' => 'Job não encontrado.'])->setStatusCode(404);
        }

        if (! $this->canAccessManagedJob($job)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado. Jobs operacionais ficam disponíveis para admin, gestor ou RH; o DPO mantém foco em auditoria e compliance.'])->setStatusCode(403);
        }

        return $this->response->setJSON([
            'success' => true,
            'job' => $this->asyncJobService->presentJobStatus($job, sp_async_job_download_url($jobId)),
        ]);
    }

    public function download(string $jobId)
    {
        helper('file_upload');
        $this->requireAuth();

        $job = $this->asyncJobService->getJobStatus($jobId);
        if (! $job) {
            return $this->response->setJSON(['success' => false, 'message' => 'Job não encontrado.'])->setStatusCode(404);
        }

        if (! $this->canAccessManagedJob($job)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado. Jobs operacionais ficam disponíveis para admin, gestor ou RH; o DPO mantém foco em auditoria e compliance.'])->setStatusCode(403);
        }

        $safePath = supportponto_safe_download_path((string) ($job['result_file_path'] ?? ''));

        if (($job['status'] ?? null) !== AsyncJobService::STATUS_COMPLETED) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo ainda não está disponível.'])->setStatusCode(409);
        }

        if ($safePath === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo do job não encontrado ou indisponível para download.'])->setStatusCode(404);
        }

        return $this->response->download($safePath, null)->setFileName(basename($safePath));
    }

    private function canAccessManagedJob(array $job): bool
    {
        $ownerId = (int) ($job['employee_id'] ?? 0);
        $currentUserId = (int) ($this->currentUser->id ?? 0);

        if ($ownerId > 0 && $ownerId == $currentUserId) {
            return true;
        }

        $ownerContext = $this->resolveJobOwnerContext($ownerId);
        if ($ownerContext === null) {
            return false;
        }

        return $this->authorizationService->canAccessAsyncJob($this->currentUser, $ownerContext);
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

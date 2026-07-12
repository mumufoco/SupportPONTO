<?php

namespace App\Controllers\API;

use App\Models\EmployeeModel;
use App\Models\ReportQueueModel;

class ReportController extends BaseApiController
{
    protected EmployeeModel $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = new EmployeeModel();
        helper(['file_upload', 'operational_link']);
    }

    /**
     * Get report queue status by job id.
     */
    public function status(string $jobId)
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $queueModel = new ReportQueueModel();
        $job = $queueModel->getByJobId($jobId);

        if (! $job) {
            return $this->failStandard('report_not_found', 'Relatório não encontrado.', 404);
        }

        if (! $this->canAccessManagedReportJob($employee, $job)) {
            return $this->failStandard('forbidden', 'Sem permissão para acessar este relatório. Jobs operacionais ficam disponíveis para admin, gestor ou RH; o DPO mantém foco em auditoria e compliance.', 403);
        }

        return $this->respondStandard(
            $this->presentJobStatus($job),
            'Status do relatório carregado com sucesso.',
            200,
            'report_status_success'
        );
    }

    public function download(string $jobId)
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $queueModel = new ReportQueueModel();
        $job = $queueModel->getByJobId($jobId);

        if (! $job) {
            return $this->failStandard('report_not_found', 'Relatório não encontrado.', 404);
        }

        if (! $this->canAccessManagedReportJob($employee, $job)) {
            return $this->failStandard('forbidden', 'Sem permissão para acessar este relatório.', 403);
        }

        if ((string) ($job->status ?? '') !== 'completed') {
            return $this->failStandard('report_not_ready', 'Relatório ainda não está pronto para download.', 409);
        }

        $safePath = supportponto_safe_download_path((string) ($job->result_file_path ?? ''));
        if ($safePath === null) {
            return $this->failStandard('report_file_not_found', 'Arquivo do relatório não encontrado.', 404);
        }

        return $this->response->download($safePath, null)->setFileName(basename($safePath));
    }

    private function presentJobStatus(object $job): array
    {
        $status = (string) ($job->status ?? 'pending');
        $downloadReady = $status === 'completed' && ! empty($job->result_file_path);

        return [
            'job_id' => (string) ($job->job_id ?? ''),
            'status' => $status,
            'progress' => (int) ($job->progress ?? 0),
            'error_message' => $job->error_message,
            'completed_at' => $job->completed_at,
            'download_ready' => $downloadReady,
            'download_url' => $downloadReady ? sp_api_reports_download_url((string) ($job->job_id ?? '')) : null,
        ];
    }

    private function canAccessManagedReportJob(object $employee, object $job): bool
    {
        $ownerId = (int) $job->employee_id;

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

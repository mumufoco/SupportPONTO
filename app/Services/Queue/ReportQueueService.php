<?php

namespace App\Services\Queue;

use App\Models\EmployeeModel;
use App\Models\ReportQueueModel;
use App\Services\EmailService;
use App\Services\ReportService;
use Exception;

/**
 * @deprecated Mantido para compatibilidade com filas legadas de relatório.
 *             Novos fluxos devem preferir ReportCoordinatorService + AsyncJobService.
 */
class ReportQueueService
{
    protected ReportQueueModel $queueModel;
    protected ReportService $reportService;
    protected EmailService $emailService;
    protected EmployeeModel $employeeModel;

    public function __construct(
        ?ReportQueueModel $queueModel = null,
        ?ReportService $reportService = null,
        ?EmailService $emailService = null,
        ?EmployeeModel $employeeModel = null,
    ) {
        $this->queueModel = $queueModel ?? new ReportQueueModel();
        $this->reportService = $reportService ?? \Config\Services::reportService(false);
        $this->emailService = $emailService ?? new EmailService();
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
    }

    /**
     * @return array{success: bool, job_id?: string, error?: string}
     */
    public function enqueue(
        int $employeeId,
        string $reportType,
        string $format,
        array $filters = []
    ): array {
        try {
            $jobData = [
                'employee_id'   => $employeeId,
                'report_type'   => $reportType,
                'report_format' => $format,
                'filters'       => json_encode($filters),
                'status'        => 'pending',
            ];

            $jobId = $this->queueModel->insert($jobData);

            if (! $jobId) {
                return [
                    'success' => false,
                    'error'   => 'Failed to enqueue report job',
                ];
            }

            $job = $this->queueModel->find($jobId);

            log_message('info', "Report queued: {$job->job_id} for employee {$employeeId}");

            return [
                'success' => true,
                'job_id'  => $job->job_id,
            ];
        } catch (Exception $e) {
            log_message('error', 'Failed to enqueue report: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, processed: bool, job_id?: string, error?: string, message?: string}
     */
    public function processNext(): array
    {
        $jobs = $this->queueModel->getPendingJobs(1);

        if (empty($jobs)) {
            return [
                'success'   => true,
                'processed' => false,
                'message'   => 'No pending jobs',
            ];
        }

        return $this->processJob($jobs[0]);
    }

    /**
     * @return array{success: bool, processed: int, failed: int}
     */
    public function processAll(int $maxJobs = 10): array
    {
        $jobs = $this->queueModel->getPendingJobs($maxJobs);

        $processed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $result = $this->processJob($job);

            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
            }
        }

        return [
            'success'   => true,
            'processed' => $processed,
            'failed'    => $failed,
        ];
    }

    /**
     * @return array{success: bool, job_id: string, error?: string}
     */
    protected function processJob(object $job): array
    {
        try {
            log_message('info', "Processing report job: {$job->job_id}");

            $this->queueModel->markAsProcessing($job->job_id);
            $filters = json_decode($job->filters, true) ?? [];
            $this->queueModel->updateProgress($job->job_id, 10);

            $result = $this->reportService->generateReportData($job->report_type, $filters);

            if (! ($result['success'] ?? false)) {
                throw new Exception($result['error'] ?? 'Failed to generate report data');
            }

            $this->queueModel->updateProgress($job->job_id, 50);
            $data = $result['data'];

            $filePath = $this->generateOutputFile(
                $job->report_type,
                $data,
                $job->report_format,
                $filters,
                $job->job_id
            );

            $this->queueModel->updateProgress($job->job_id, 90);
            $this->queueModel->markAsCompleted($job->job_id, $filePath);
            $this->sendCompletionEmail($job, $filePath);

            log_message('info', "Report job completed: {$job->job_id}");

            return [
                'success' => true,
                'job_id'  => $job->job_id,
            ];
        } catch (Exception $e) {
            log_message('error', "Report job failed: {$job->job_id} - " . $e->getMessage());

            $this->queueModel->markAsFailed($job->job_id, $e->getMessage());
            $this->sendFailureEmail($job, $e->getMessage());

            return [
                'success' => false,
                'job_id'  => $job->job_id,
                'error'   => $e->getMessage(),
            ];
        }
    }

    protected function generateOutputFile(
        string $type,
        array $data,
        string $format,
        array $filters,
        string $jobId
    ): string {
        $result = $this->reportService->generateReport($type, $data, $format, $filters);

        if (! ($result['success'] ?? false)) {
            throw new Exception($result['error'] ?? 'Erro ao gerar arquivo de relatório');
        }

        $sourcePath = (string) ($result['filepath'] ?? '');
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw new Exception('Arquivo de relatório não encontrado após geração');
        }

        $outputDir = WRITEPATH . 'reports/';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $sourceExtension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $finalExtension = $sourceExtension !== '' ? $sourceExtension : strtolower($format);
        $filePath = $outputDir . sprintf('%s_%s.%s', $type, $jobId, $finalExtension);

        if ($sourcePath !== $filePath && ! @rename($sourcePath, $filePath)) {
            if (! @copy($sourcePath, $filePath)) {
                throw new Exception('Não foi possível persistir o arquivo final do relatório');
            }
            @unlink($sourcePath);
        }

        return $filePath;
    }

    protected function sendCompletionEmail(object $job, string $filePath): void
    {
        try {
            helper('operational_link');
            $employee = $this->employeeModel->find($job->employee_id);

            if (! $employee || ! $employee->email) {
                return;
            }

            $downloadUrl = route_to('reports.download', $job->job_id);

            $subject = 'Relatório Pronto para Download';
            $message = "
                <h2>Seu relatório está pronto!</h2>
                <p>O relatório <strong>{$job->report_type}</strong> que você solicitou foi gerado com sucesso.</p>
                <p><a href=\"{$downloadUrl}\" style=\"background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Baixar Relatório</a></p>
                <p><small>Este link expirará em 7 dias.</small></p>
            ";

            $this->emailService->send($employee->email, $subject, $message);
        } catch (Exception $e) {
            log_message('warning', 'Failed to send completion email: ' . $e->getMessage());
        }
    }

    protected function sendFailureEmail(object $job, string $errorMessage): void
    {
        try {
            $employee = $this->employeeModel->find($job->employee_id);

            if (! $employee || ! $employee->email) {
                return;
            }

            $subject = 'Erro ao Gerar Relatório';
            $message = "
                <h2>Erro ao gerar relatório</h2>
                <p>Infelizmente houve um erro ao gerar o relatório <strong>{$job->report_type}</strong>.</p>
                <p><strong>Erro:</strong> {$errorMessage}</p>
                <p>Por favor, entre em contato com o suporte ou tente novamente.</p>
            ";

            $this->emailService->send($employee->email, $subject, $message);
        } catch (Exception $e) {
            log_message('warning', 'Failed to send failure email: ' . $e->getMessage());
        }
    }

    public function getStatus(string $jobId): ?array
    {
        $job = $this->queueModel->getByJobId($jobId);

        if (! $job) {
            return null;
        }

        return [
            'job_id'       => $job->job_id,
            'status'       => $job->status,
            'progress'     => $job->progress,
            'created_at'   => $job->created_at,
            'started_at'   => $job->started_at,
            'completed_at' => $job->completed_at,
            'error'        => $job->error_message,
            'download_url' => $job->status === 'completed'
                ? route_to('reports.download', $job->job_id)
                : null,
        ];
    }
}

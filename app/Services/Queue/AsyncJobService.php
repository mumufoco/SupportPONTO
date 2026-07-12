<?php

namespace App\Services\Queue;

use App\DTO\Queue\AsyncJobStatusData;
use App\Models\AsyncJobModel;
use App\Models\AuditModel;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Biometric\FaceRecognitionService;
use App\Services\LGPD\DataExportService;
use App\Services\Notification\PushNotificationService;
use App\Services\Reports\ReportExecutionService;
use App\Services\Queue\Support\AsyncJobTypeCatalog;
use Config\Queue as QueueConfig;
use RuntimeException;
use Throwable;

class AsyncJobService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_REPORT_GENERATE = 'report.generate';
    public const TYPE_LGPD_EXPORT = 'lgpd.export';
    public const TYPE_PUSH_NOTIFICATION = 'notification.push';
    public const TYPE_FACE_ENROLL = 'biometric.face_enroll';
    public const TYPE_DATABASE_BACKUP = 'admin.database_backup';

    protected AsyncJobModel $jobModel;
    protected ReportExecutionService $reportExecutionService;
    protected DataExportService $dataExportService;
    protected PushNotificationService $pushNotificationService;
    protected FaceRecognitionService $faceRecognitionService;
    protected DatabaseBackupService $databaseBackupService;
    protected AuditModel $auditModel;
    protected QueueConfig $queueConfig;
    protected AsyncJobTypeCatalog $jobTypeCatalog;

    public function __construct()
    {
        $this->jobModel = new AsyncJobModel();
        $this->reportExecutionService = new ReportExecutionService();
        $this->dataExportService = new DataExportService();
        $this->pushNotificationService = new PushNotificationService();
        $this->faceRecognitionService = new FaceRecognitionService();
        $this->databaseBackupService = new DatabaseBackupService();
        $this->auditModel = new AuditModel();
        $this->queueConfig = config('Queue');
        $this->jobTypeCatalog = new AsyncJobTypeCatalog();
    }

    public function enqueue(string $jobType, array $payload, array $options = []): array
    {
        $data = [
            'job_type' => $jobType,
            'queue' => $options['queue'] ?? $this->resolveQueue($jobType),
            'employee_id' => $options['employee_id'] ?? null,
            'priority' => $options['priority'] ?? $this->resolvePriority($jobType),
            'max_attempts' => $options['max_attempts'] ?? 3,
            'trace_id' => $options['trace_id'] ?? service('request')->getHeaderLine('X-Request-ID') ?: null,
            'payload' => $payload,
            'available_at' => $options['available_at'] ?? date('Y-m-d H:i:s'),
            'status' => self::STATUS_PENDING,
        ];

        $this->guardPayloadSize($data['payload']);

        $id = $this->jobModel->insert($data, true);
        if (! $id) {
            throw new RuntimeException('Falha ao enfileirar job assíncrono.');
        }

        $job = $this->jobModel->find($id);

        log_message('info', 'Async job enqueued: {job_id} ({job_type})', [
            'job_id' => $job->job_id,
            'job_type' => $jobType,
            'queue' => $job->queue,
        ]);

        return $this->formatJob($job);
    }

    public function getJobStatus(string $jobId): ?array
    {
        $job = $this->jobModel->getByJobId($jobId);
        return $job ? $this->formatJob($job) : null;
    }

    public function presentJobStatus(array $job, ?string $downloadUrl = null): array
    {
        return AsyncJobStatusData::fromArray($job, $downloadUrl)->toArray();
    }

    public function processPending(int $limit = 10, ?string $queue = null, bool $recoverStale = true, int $staleAfterMinutes = 15): array
    {
        if (! is_cli()) {
            throw new RuntimeException('O processamento de jobs assíncronos deve ser executado apenas via CLI segura.');
        }

        $recovered = 0;
        $movedToRetry = 0;
        $forcedFailed = 0;

        if ($recoverStale) {
            $recovery = $this->recoverStaleJobs($staleAfterMinutes, $queue, max($limit, 10));
            $recovered = $recovery['recovered'];
            $movedToRetry = $recovery['retried'];
            $forcedFailed = $recovery['failed'];
        }

        $workerId = $this->workerId();
        $jobs = $this->jobModel->claimPendingJobs($limit, $queue, $workerId);
        $processed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $result = $this->processClaimedJob($job);
            if ($result['success']) {
                $processed++;
            } elseif (($result['status'] ?? null) === self::STATUS_FAILED) {
                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'recovered' => $recovered,
            'recovered_to_retry' => $movedToRetry,
            'recovered_to_failed' => $forcedFailed,
        ];
    }

    public function recoverStaleJobs(int $staleAfterMinutes = 15, ?string $queue = null, int $limit = 50): array
    {
        $staleJobs = $this->jobModel->getStaleProcessingJobs($staleAfterMinutes, $limit, $queue);
        $recovered = 0;
        $retried = 0;
        $failed = 0;

        foreach ($staleJobs as $job) {
            $recovered++;
            $attempts = (int) ($job->attempts ?? 0);
            $maxAttempts = (int) ($job->max_attempts ?? 3);
            $message = sprintf(
                'Job recuperado automaticamente após ficar preso em processing por mais de %d minuto(s).',
                max(1, $staleAfterMinutes)
            );

            if ($attempts < $maxAttempts) {
                $delaySeconds = min(300, max(30, 15 * max(1, $attempts)));
                $this->jobModel->markForRetry($job->job_id, $message, $delaySeconds);
                $retried++;
                log_message('warning', 'Async job recovered to retry: {job_id}', ['job_id' => $job->job_id]);
                continue;
            }

            $this->jobModel->markAsFailed($job->job_id, $message);
            $failed++;
            log_message('error', 'Async job forced to failed after stale recovery: {job_id}', ['job_id' => $job->job_id]);
        }

        return [
            'success' => true,
            'recovered' => $recovered,
            'retried' => $retried,
            'failed' => $failed,
        ];
    }

    public function processClaimedJob(object $job): array
    {
        return $this->executeClaimedJob($job);
    }

    public function processJob(string $jobId): array
    {
        $job = $this->jobModel->getByJobId($jobId);
        if (! $job) {
            return ['success' => false, 'status' => self::STATUS_FAILED, 'error' => 'Job não encontrado.'];
        }

        if (! $this->jobModel->markAsProcessing($jobId, $this->workerId())) {
            return ['success' => false, 'status' => 'skipped', 'job_id' => $jobId, 'error' => 'Job já foi reivindicado por outro worker ou não está disponível.'];
        }
        $job = $this->jobModel->getByJobId($jobId);

        try {
            $payload = $this->decodeJson($job->payload);
            $this->jobModel->updateProgress($jobId, 15);
            $result = match ($job->job_type) {
                self::TYPE_REPORT_GENERATE => $this->handleReportGenerate($payload, $job),
                self::TYPE_LGPD_EXPORT => $this->handleLgpdExport($payload),
                self::TYPE_PUSH_NOTIFICATION => $this->handlePushNotification($payload),
                self::TYPE_FACE_ENROLL => $this->handleFaceEnrollment($payload),
                self::TYPE_DATABASE_BACKUP => $this->handleDatabaseBackup(),
                default => throw new RuntimeException('Tipo de job não suportado: ' . $job->job_type),
            };

            $this->jobModel->markAsCompleted($jobId, $result['result_payload'] ?? $result, $result['result_file_path'] ?? null);

            return ['success' => true, 'status' => self::STATUS_COMPLETED, 'job_id' => $jobId];
        } catch (Throwable $e) {
            $job = $this->jobModel->getByJobId($jobId);
            $attempts = (int) ($job->attempts ?? 1);
            $maxAttempts = (int) ($job->max_attempts ?? 3);
            $message = $e->getMessage();
            log_message('error', 'Async job failed: {job_id} {error}', ['job_id' => $jobId, 'error' => $message]);

            if ($attempts < $maxAttempts) {
                $delaySeconds = min(300, 30 * $attempts);
                $this->jobModel->markForRetry($jobId, $message, $delaySeconds);
                return ['success' => false, 'status' => self::STATUS_RETRYING, 'job_id' => $jobId, 'error' => $message];
            }

            $this->jobModel->markAsFailed($jobId, $message);
            return ['success' => false, 'status' => self::STATUS_FAILED, 'job_id' => $jobId, 'error' => $message];
        }
    }

    protected function executeClaimedJob(object $job): array
    {
        $jobId = (string) $job->job_id;

        try {
            $payload = $this->decodeJson($job->payload);
            $this->jobModel->updateProgress($jobId, 15);
            $result = match ($job->job_type) {
                self::TYPE_REPORT_GENERATE => $this->handleReportGenerate($payload, $job),
                self::TYPE_LGPD_EXPORT => $this->handleLgpdExport($payload),
                self::TYPE_PUSH_NOTIFICATION => $this->handlePushNotification($payload),
                self::TYPE_FACE_ENROLL => $this->handleFaceEnrollment($payload),
                self::TYPE_DATABASE_BACKUP => $this->handleDatabaseBackup(),
                default => throw new RuntimeException('Tipo de job não suportado: ' . $job->job_type),
            };

            $this->jobModel->markAsCompleted($jobId, $result['result_payload'] ?? $result, $result['result_file_path'] ?? null);

            return ['success' => true, 'status' => self::STATUS_COMPLETED, 'job_id' => $jobId];
        } catch (Throwable $e) {
            $freshJob = $this->jobModel->getByJobId($jobId);
            $attempts = (int) ($freshJob->attempts ?? $job->attempts ?? 1);
            $maxAttempts = (int) ($freshJob->max_attempts ?? $job->max_attempts ?? 3);
            $message = $e->getMessage();
            log_message('error', 'Async job failed: {job_id} {error}', ['job_id' => $jobId, 'error' => $message]);

            if ($attempts < $maxAttempts) {
                $delaySeconds = min(300, 30 * max(1, $attempts));
                $this->jobModel->markForRetry($jobId, $message, $delaySeconds);
                return ['success' => false, 'status' => self::STATUS_RETRYING, 'job_id' => $jobId, 'error' => $message];
            }

            $this->jobModel->markAsFailed($jobId, $message);
            return ['success' => false, 'status' => self::STATUS_FAILED, 'job_id' => $jobId, 'error' => $message];
        }
    }

    protected function handleReportGenerate(array $payload, object $job): array
    {
        $type = (string) ($payload['type'] ?? '');
        $format = (string) ($payload['format'] ?? 'pdf');
        $filters = $payload['filters'] ?? [];

        $this->jobModel->updateProgress($job->job_id, 35);
        if ($format === 'afd' || $type === 'afd') {
            $data = $this->reportExecutionService->buildAfdData($filters, $payload['department_restriction'] ?? null);
            $result = ['success' => true, 'data' => $data];
        } else {
            $result = $this->reportExecutionService->generateReportData($type, $filters);
        }

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Falha ao gerar dados do relatório.');
        }

        $this->jobModel->updateProgress($job->job_id, 70);
        $formatted = $this->reportExecutionService->formatReport($format === 'afd' ? 'afd' : $type, $result['data'], $format, $filters);
        if (! ($formatted['success'] ?? false)) {
            throw new RuntimeException($formatted['error'] ?? 'Falha ao gerar arquivo do relatório.');
        }

        if (($formatted['kind'] ?? null) !== 'download') {
            return ['result_payload' => $formatted['payload'] ?? $formatted];
        }

        return [
            'result_payload' => [
                'type' => $type,
                'format' => $format,
                'filename' => $formatted['filename'],
            ],
            'result_file_path' => $formatted['filepath'],
        ];
    }

    protected function handleLgpdExport(array $payload): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $requestedBy = $payload['requested_by'] ?? null;
        $result = $this->dataExportService->exportUserData($employeeId, $requestedBy);
        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Falha ao exportar dados LGPD.');
        }

        $exportId = $result['export_id'] ?? null;
        return [
            'result_payload' => $result,
            'result_file_path' => $exportId ? WRITEPATH . 'exports/lgpd/' . $exportId . '.zip' : null,
        ];
    }

    protected function handlePushNotification(array $payload): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $title = (string) ($payload['title'] ?? 'Notificação');
        $body = (string) ($payload['body'] ?? '');
        $data = $payload['data'] ?? [];
        $options = $payload['options'] ?? [];

        $result = $this->pushNotificationService->sendToEmployee($employeeId, $title, $body, $data, $options);
        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? $result['message'] ?? 'Falha ao enviar push notification.');
        }

        return ['result_payload' => $result];
    }

    protected function handleFaceEnrollment(array $payload): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $photo = (string) ($payload['photo'] ?? '');
        $force = (bool) ($payload['force'] ?? false);
        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : ($employeeId > 0 ? $employeeId : null);
        $actorRole = (string) ($payload['actor_role'] ?? '');
        $requestIp = (string) ($payload['request_ip'] ?? '');
        $userAgent = (string) ($payload['user_agent'] ?? '');
        $previousImageHash = (string) ($payload['previous_image_hash'] ?? '');

        $result = $this->faceRecognitionService->enrollFace($employeeId, $photo, $force, [
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,
        ]);
        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Falha ao processar biometria facial.');
        }

        $previousImageHash = (string) ($result['previous_image_hash'] ?? $previousImageHash);

        $this->auditModel->log(
            $actorId,
            $force ? 'BIOMETRIC_REENROLL_COMPLETED' : 'BIOMETRIC_ENROLL_COMPLETED',
            'biometric_templates',
            $result['template_id'] ?? null,
            $force ? [
                'employee_id' => $employeeId,
                'image_hash' => $previousImageHash !== '' ? $previousImageHash : null,
            ] : null,
            [
                'employee_id' => $employeeId,
                're_enroll' => $force,
                'confidence' => $result['confidence'] ?? null,
                'image_hash' => $result['image_hash'] ?? null,
                'indexed_faces' => $result['rebuild']['indexed_faces'] ?? null,
                'actor_role' => $actorRole !== '' ? $actorRole : null,
                'request_ip' => $requestIp !== '' ? $requestIp : null,
                'user_agent' => $userAgent !== '' ? $userAgent : null,
                'previous_image_hash' => $previousImageHash !== '' ? $previousImageHash : null,
                'audit_action' => $force ? 'FORCED_REENROLL' : 'ENROLL',
            ],
            $force ? 'Recadastro facial processado com sucesso via job assíncrono.' : 'Cadastro facial processado com sucesso via job assíncrono.',
            'info'
        );

        return ['result_payload' => $result];
    }

    protected function handleDatabaseBackup(): array
    {
        $result = $this->databaseBackupService->createBackup();
        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Falha ao gerar backup do banco.');
        }

        return [
            'result_payload' => $result,
            'result_file_path' => $result['filepath'] ?? null,
        ];
    }

    protected function resolveQueue(string $jobType): string
    {
        return $this->jobTypeCatalog->queueFor($jobType);
    }

    protected function resolvePriority(string $jobType): int
    {
        return $this->jobTypeCatalog->priorityFor($jobType);
    }

    /**
     * Enfileira notificação push sem processar envio dentro da requisição web.
     */
    public function dispatchPushNotification(array $payload, array $options = []): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? $options['employee_id'] ?? 0);
        $messagePayload = $payload['payload'] ?? [];

        return $this->enqueue(self::TYPE_PUSH_NOTIFICATION, [
            'employee_id' => $employeeId,
            'title' => (string) ($messagePayload['title'] ?? $payload['title'] ?? 'SupportPONTO'),
            'body' => (string) ($messagePayload['body'] ?? $payload['body'] ?? 'Notificação'),
            'data' => $payload['data'] ?? ['type' => $payload['type'] ?? 'generic'],
            'options' => $payload['options'] ?? [],
        ], [
            'employee_id' => $employeeId > 0 ? $employeeId : null,
            'queue' => 'notifications',
            'priority' => $options['priority'] ?? 90,
            'max_attempts' => $options['max_attempts'] ?? 3,
        ]);
    }

    public function workerId(): string
    {
        static $workerId = null;

        if ($workerId === null) {
            $workerId = 'sp_worker_' . getmypid() . '_' . bin2hex(random_bytes(4));
        }

        return $workerId;
    }

    /**
     * Evita que fotos/base64 ou relatórios enormes sejam gravados sem limite na tabela de jobs.
     */
    protected function guardPayloadSize(array $payload): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $bytes = is_string($encoded) ? strlen($encoded) : 0;
        $maxBytes = max(1024 * 1024, (int) env('QUEUE_MAX_PAYLOAD_BYTES', 8 * 1024 * 1024));

        if ($bytes > $maxBytes) {
            throw new RuntimeException(sprintf(
                'Payload do job excede o limite configurado de %d bytes.',
                $maxBytes
            ));
        }
    }

    protected function formatJob(object $job): array
    {
        return [
            'job_id' => $job->job_id,
            'job_type' => $job->job_type,
            'queue' => $job->queue,
            'status' => $job->status,
            'progress' => (int) ($job->progress ?? 0),
            'attempts' => (int) ($job->attempts ?? 0),
            'max_attempts' => (int) ($job->max_attempts ?? 0),
            'available_at' => $job->available_at,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
            'result' => $this->decodeJson($job->result_payload),
            'result_file_path' => $job->result_file_path,
            'output_expires_at' => $job->output_expires_at ?? null,
            'purged_at' => $job->purged_at ?? null,
            'last_error' => $job->last_error,
        ];
    }

    protected function decodeJson($value): array
    {
        // PostgreSQL JSONB columns may be returned as arrays containing stdClass objects.
        // Force full re-serialization to guarantee all nested values are plain PHP arrays.
        if (is_array($value)) {
            $clean = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
            return is_array($clean) ? $clean : $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

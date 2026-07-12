<?php

declare(strict_types=1);

namespace App\DTO\Queue;

/**
 * DTO simples para padronizar a saída pública de status de jobs assíncronos.
 *
 * Mantém o contrato em array usado por controllers/views, mas centraliza casts,
 * valores padrão e cálculo de disponibilidade de download.
 */
final class AsyncJobStatusData
{
    /**
     * @param array<string, mixed> $result
     */
    public function __construct(
        public readonly ?string $jobId,
        public readonly ?string $jobType,
        public readonly ?string $queue,
        public readonly string $status,
        public readonly int $progress,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly mixed $availableAt,
        public readonly mixed $startedAt,
        public readonly mixed $completedAt,
        public readonly mixed $outputExpiresAt,
        public readonly mixed $purgedAt,
        public readonly array $result,
        public readonly ?string $lastError,
        public readonly ?string $resultFilePath,
        public readonly ?string $downloadUrl = null,
    ) {
    }

    /**
     * @param array<string, mixed> $job
     */
    public static function fromArray(array $job, ?string $downloadUrl = null): self
    {
        $status = (string) ($job['status'] ?? 'pending');
        $result = $job['result'] ?? [];

        return new self(
            jobId: isset($job['job_id']) ? (string) $job['job_id'] : null,
            jobType: isset($job['job_type']) ? (string) $job['job_type'] : null,
            queue: isset($job['queue']) ? (string) $job['queue'] : null,
            status: $status,
            progress: (int) ($job['progress'] ?? 0),
            attempts: (int) ($job['attempts'] ?? 0),
            maxAttempts: (int) ($job['max_attempts'] ?? 0),
            availableAt: $job['available_at'] ?? null,
            startedAt: $job['started_at'] ?? null,
            completedAt: $job['completed_at'] ?? null,
            outputExpiresAt: $job['output_expires_at'] ?? null,
            purgedAt: $job['purged_at'] ?? null,
            result: is_array($result) ? $result : [],
            lastError: isset($job['last_error']) ? (string) $job['last_error'] : null,
            resultFilePath: isset($job['result_file_path']) ? (string) $job['result_file_path'] : null,
            downloadUrl: $downloadUrl,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $downloadReady = $this->status === 'completed' && $this->resultFilePath !== null && $this->resultFilePath !== '';

        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'queue' => $this->queue,
            'status' => $this->status,
            'progress' => $this->progress,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'available_at' => $this->availableAt,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'output_expires_at' => $this->outputExpiresAt,
            'purged_at' => $this->purgedAt,
            'result' => $this->result,
            'last_error' => $this->lastError,
            'download_ready' => $downloadReady,
            'download_url' => $downloadReady ? $this->downloadUrl : null,
        ];
    }
}

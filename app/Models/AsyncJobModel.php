<?php

namespace App\Models;

use CodeIgniter\Model;

class AsyncJobModel extends Model
{
    protected $table = 'async_jobs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'job_id',
        'job_type',
        'queue',
        'employee_id',
        'status',
        'priority',
        'progress',
        'attempts',
        'max_attempts',
        'payload',
        'result_payload',
        'result_file_path',
        'trace_id',
        'locked_by',
        'locked_at',
        'output_expires_at',
        'purged_at',
        'last_error',
        'available_at',
        'started_at',
        'completed_at',
    ];
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $beforeInsert = ['assignJobId', 'normalizeJson'];
    protected $beforeUpdate = ['normalizeJson'];

    protected function assignJobId(array $data): array
    {
        if (empty($data['data']['job_id'])) {
            $data['data']['job_id'] = 'job_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
        }

        return $data;
    }

    protected function normalizeJson(array $data): array
    {
        foreach (['payload', 'result_payload'] as $field) {
            if (isset($data['data'][$field]) && is_array($data['data'][$field])) {
                $data['data'][$field] = json_encode($data['data'][$field], JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    public function getByJobId(string $jobId): ?object
    {
        return $this->where('job_id', $jobId)->first();
    }

    public function getPendingJobs(int $limit = 10, ?string $queue = null): array
    {
        $builder = $this->groupStart()
            ->where('status', 'pending')
            ->orWhere('status', 'retrying')
            ->groupEnd()
            ->groupStart()
            ->where('available_at', null)
            ->orWhere('available_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->limit($limit);

        if ($queue !== null) {
            $builder->where('queue', $queue);
        }

        return $builder->findAll();
    }


    /**
     * Reivindica jobs pendentes de forma segura para reduzir risco de processamento duplo
     * quando houver mais de um worker em produção.
     *
     * @return array<int, object>
     */
    public function claimPendingJobs(int $limit = 10, ?string $queue = null, ?string $workerId = null): array
    {
        $workerId = $workerId ?: ('worker_' . getmypid() . '_' . bin2hex(random_bytes(4)));
        $claimed = [];
        $candidates = $this->getPendingJobs($limit, $queue);

        foreach ($candidates as $candidate) {
            if (count($claimed) >= $limit) {
                break;
            }

            if ($this->markAsProcessing((string) $candidate->job_id, $workerId)) {
                $job = $this->getByJobId((string) $candidate->job_id);
                if ($job) {
                    $claimed[] = $job;
                }
            }
        }

        return $claimed;
    }

    public function getStaleProcessingJobs(int $staleAfterMinutes = 15, int $limit = 50, ?string $queue = null): array
    {
        $threshold = date('Y-m-d H:i:s', time() - max(1, $staleAfterMinutes) * 60);

        $builder = $this->where('status', 'processing')
            ->where('started_at IS NOT NULL', null, false)
            ->where('started_at <=', $threshold)
            ->orderBy('started_at', 'ASC')
            ->limit($limit);

        if ($queue !== null) {
            $builder->where('queue', $queue);
        }

        return $builder->findAll();
    }

    public function markAsProcessing(string $jobId, ?string $workerId = null): bool
    {
        $builder = $this->where('job_id', $jobId)
            ->groupStart()
            ->where('status', 'pending')
            ->orWhere('status', 'retrying')
            ->groupEnd()
            ->groupStart()
            ->where('available_at', null)
            ->orWhere('available_at <=', date('Y-m-d H:i:s'))
            ->groupEnd();

        $builder->set([
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'locked_by' => $workerId,
            'locked_at' => date('Y-m-d H:i:s'),
            'attempts' => new \CodeIgniter\Database\RawSql('attempts + 1'),
        ])->update();

        return $this->db->affectedRows() > 0;
    }

    public function updateProgress(string $jobId, int $progress): bool
    {
        return (bool) $this->where('job_id', $jobId)->set('progress', max(0, min(100, $progress)))->update();
    }

    public function markAsCompleted(string $jobId, array $resultPayload = [], ?string $filePath = null, ?int $outputExpiresInHours = null): bool
    {
        $expiresAt = null;
        if ($filePath !== null && $filePath !== '') {
            $ttl = max(1, $outputExpiresInHours ?? (int) env('QUEUE_RESULT_FILES_TTL_HOURS', 72));
            $expiresAt = date('Y-m-d H:i:s', time() + ($ttl * 3600));
        }

        return (bool) $this->where('job_id', $jobId)->set([
            'status' => 'completed',
            'progress' => 100,
            'result_payload' => $resultPayload,
            'result_file_path' => $filePath,
            'output_expires_at' => $expiresAt,
            'locked_by' => null,
            'locked_at' => null,
            'last_error' => null,
            'completed_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function markResultPurged(string $jobId): bool
    {
        return (bool) $this->where('job_id', $jobId)->set([
            'result_file_path' => null,
            'purged_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function markForRetry(string $jobId, string $errorMessage, int $delaySeconds = 60): bool
    {
        return (bool) $this->where('job_id', $jobId)->set([
            'status' => 'retrying',
            'progress' => 0,
            'last_error' => $errorMessage,
            'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'started_at' => null,
            'completed_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ])->update();
    }

    public function markAsFailed(string $jobId, string $errorMessage): bool
    {
        return (bool) $this->where('job_id', $jobId)->set([
            'status' => 'failed',
            'last_error' => $errorMessage,
            'locked_by' => null,
            'locked_at' => null,
            'completed_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

}

<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Report Queue Model
 *
 * Manages background report generation jobs
 */
class ReportQueueModel extends Model
{
    protected $table            = 'report_queue';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'job_id',
        'employee_id',
        'report_type',
        'report_format',
        'filters',
        'status',
        'progress',
        'result_file_path',
        'error_message',
        'attempts',
        'max_attempts',
        'started_at',
        'completed_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'job_id'         => 'required|is_unique[report_queue.job_id,id,{id}]',
        'employee_id'    => 'required|integer',
        'report_type'    => 'required|max_length[50]',
        'report_format'  => 'required|in_list[pdf,excel,csv]',
        'status'         => 'in_list[pending,processing,completed,failed]',
    ];

    protected $validationMessages = [];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateJobId'];

    /**
     * Generate unique job_id before insert
     */
    protected function generateJobId(array $data): array
    {
        if (!isset($data['data']['job_id']) || empty($data['data']['job_id'])) {
            $data['data']['job_id'] = 'report_' . uniqid() . '_' . bin2hex(random_bytes(4));
        }

        return $data;
    }

    /**
     * Get pending jobs (ordered by created_at)
     *
     * @param int $limit Maximum jobs to return
     * @return array
     */
    public function getPendingJobs(int $limit = 10): array
    {
        return $this->where('status', 'pending')
            ->where('attempts <', $this->db->escapeIdentifier('max_attempts'))
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Mark job as processing
     *
     * @param string $jobId
     * @return bool
     */
    public function markAsProcessing(string $jobId): bool
    {
        return $this->where('job_id', $jobId)->set([
            'status'      => 'processing',
            'started_at'  => date('Y-m-d H:i:s'),
            'attempts'    => $this->db->raw('attempts + 1'),
        ])->update();
    }

    /**
     * Mark job as completed
     *
     * @param string $jobId
     * @param string $filePath Path to generated file
     * @return bool
     */
    public function markAsCompleted(string $jobId, string $filePath): bool
    {
        return $this->where('job_id', $jobId)->set([
            'status'           => 'completed',
            'progress'         => 100,
            'result_file_path' => $filePath,
            'completed_at'     => date('Y-m-d H:i:s'),
        ])->update();
    }

    /**
     * Mark job as failed
     *
     * @param string $jobId
     * @param string $errorMessage
     * @return bool
     */
    public function markAsFailed(string $jobId, string $errorMessage): bool
    {
        return $this->where('job_id', $jobId)->set([
            'status'        => 'failed',
            'error_message' => $errorMessage,
        ])->update();
    }

    /**
     * Update job progress
     *
     * @param string $jobId
     * @param int $progress Percentage (0-100)
     * @return bool
     */
    public function updateProgress(string $jobId, int $progress): bool
    {
        $progress = max(0, min(100, $progress)); // Clamp to 0-100

        return $this->where('job_id', $jobId)->set([
            'progress' => $progress,
        ])->update();
    }

    /**
     * Get job by job_id
     *
     * @param string $jobId
     * @return object|null
     */
    public function getByJobId(string $jobId): ?object
    {
        return $this->where('job_id', $jobId)->first();
    }

    /**
     * Clean old completed/failed jobs
     *
     * @param int $daysOld Jobs older than this many days
     * @return int Number of deleted records
     */
    public function cleanOldJobs(int $daysOld = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $count = $this->where('status', 'completed')
            ->orWhere('status', 'failed')
            ->where('completed_at <', $cutoffDate)
            ->countAllResults(false);

        if ($count > 0) {
            // Delete associated files first
            $jobs = $this->where('status', 'completed')
                ->orWhere('status', 'failed')
                ->where('completed_at <', $cutoffDate)
                ->findAll();

            foreach ($jobs as $job) {
                if ($job->result_file_path && file_exists($job->result_file_path)) {
                    @unlink($job->result_file_path);
                }
            }

            // Delete records
            $this->where('status', 'completed')
                ->orWhere('status', 'failed')
                ->where('completed_at <', $cutoffDate)
                ->delete();
        }

        return $count;
    }
}

<?php

namespace App\Services\Biometric\Face;

class FaceRecognitionLogService
{
    public function logAttempt(int $employeeId, bool $success, float $similarity, float $threshold, string $action, ?string $errorMessage = null): void
    {
        try {
            \Config\Database::connect()->table('facial_recognition_logs')->insert([
                'employee_id' => $employeeId,
                'action' => $action,
                'success' => $success ? 1 : 0,
                'similarity_score' => $similarity,
                'threshold_used' => $threshold,
                'ip_address' => service('request') ? service('request')->getIPAddress() : null,
                'user_agent' => (service('request') instanceof \CodeIgniter\HTTP\IncomingRequest) ? service('request')->getUserAgent()->getAgentString() : null,
                'error_message' => $errorMessage,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Failed to log facial recognition attempt: ' . $e->getMessage());
        }
    }
}

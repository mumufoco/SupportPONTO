<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Push Subscription Model
 *
 * Manages browser push notification subscriptions
 */
class PushSubscriptionModel extends Model
{
    protected $table            = 'push_subscriptions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'endpoint',
        'public_key',
        'auth_token',
        'user_agent',
        'active',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'employee_id' => 'required|integer',
        'endpoint'    => 'required|max_length[500]',
    ];

    protected $validationMessages = [
        'employee_id' => [
            'required' => 'ID do funcionário é obrigatório.',
        ],
        'endpoint' => [
            'required' => 'Endpoint é obrigatório.',
        ],
    ];

    /**
     * Get subscriptions for employee
     *
     * @param int $employeeId
     * @return array
     */
    public function getEmployeeSubscriptions(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('active', true)
            ->findAll();
    }

    /**
     * Get subscription by endpoint
     *
     * @param string $endpoint
     * @return object|null
     */
    public function getByEndpoint(string $endpoint): ?object
    {
        return $this->where('endpoint', $endpoint)->first();
    }

    /**
     * Subscribe employee to push notifications
     *
     * @param int    $employeeId
     * @param string $endpoint
     * @param string $publicKey
     * @param string $authToken
     * @param string $userAgent
     * @return bool|int
     */
    public function subscribe(
        int $employeeId,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $userAgent = ''
    ) {
        // Check if subscription already exists
        $existing = $this->where('employee_id', $employeeId)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing) {
            // Update existing subscription
            return $this->update($existing->id, [
                'public_key'  => $publicKey,
                'auth_token'  => $authToken,
                'user_agent'  => $userAgent,
                'active'      => true,
            ]);
        }

        // Create new subscription
        return $this->insert([
            'employee_id' => $employeeId,
            'endpoint'    => $endpoint,
            'public_key'  => $publicKey,
            'auth_token'  => $authToken,
            'user_agent'  => $userAgent,
            'active'      => true,
        ]);
    }

    /**
     * Unsubscribe employee from push notifications
     *
     * @param int    $employeeId
     * @param string $endpoint
     * @return bool
     */
    public function unsubscribe(int $employeeId, string $endpoint): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('endpoint', $endpoint)
            ->set('active', false)
            ->update();
    }

    /**
     * Delete inactive subscriptions older than X days
     *
     * @param int $days
     * @return int Number of deleted records
     */
    public function cleanupOldSubscriptions(int $days = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->where('active', false)
            ->where('updated_at <', $cutoffDate)
            ->delete();
    }

    /**
     * Get all active subscriptions for employees
     *
     * @param array $employeeIds
     * @return array
     */
    public function getActiveSubscriptionsForEmployees(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        return $this->whereIn('employee_id', $employeeIds)
            ->where('active', true)
            ->findAll();
    }
}

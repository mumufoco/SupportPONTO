<?php

namespace App\Models;

use CodeIgniter\Model;

class BiometricTemplateModel extends Model
{
    protected $table            = 'biometric_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'biometric_type',
        'template_data',
        'template_hash',
        'image_hash',
        'file_path',
        'enrollment_quality',
        'model_used',
        'active',
        'enrolled_by',
        'metadata',
        // New fields for enhanced fingerprint support
        'finger_position',
        'template_format',
        'quality_score',
        'capture_method',
        'capture_device',
        'algorithm_version',
        'is_active',
        'enrolled_at',
        'last_used_at',
        'usage_count',
        'encrypted_storage_path',
        'encryption_version',
        'retention_until',
        'legal_basis',
        'consent_id',
        'privacy_status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'employee_id'    => 'required|integer',
        'biometric_type' => 'required|in_list[face,fingerprint]',
    ];

    protected $validationMessages = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get active biometric template for employee
     */
    public function getActive(int $employeeId, string $type): ?object
    {
        return $this->where('employee_id', $employeeId)
            ->where('biometric_type', $type)
            ->where('active', true)
            ->first();
    }

    /**
     * Get all templates for employee
     */
    public function getByEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->findAll();
    }

    /**
     * Deactivate all templates of a type for an employee
     */
    public function deactivateType(int $employeeId, string $type): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('biometric_type', $type)
            ->set(['active' => false])
            ->update();
    }

    /**
     * Check if employee has biometric enrolled
     */
    public function hasEnrolled(int $employeeId, string $type): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('biometric_type', $type)
            ->where('active', true)
            ->countAllResults() > 0;
    }

    /**
     * Get total enrolled by type
     */
    public function getTotalEnrolled(string $type): int
    {
        return $this->where('biometric_type', $type)
            ->where('active', true)
            ->countAllResults();
    }
}

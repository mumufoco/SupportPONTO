<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeChangeRequestModel extends Model
{
    protected $table            = 'employee_change_requests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'requested_by',
        'field_key',
        'field_label',
        'current_value',
        'requested_value',
        'justification',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'employee_id'     => 'required|integer',
        'requested_by'    => 'required|integer',
        'field_key'       => 'required|max_length[100]',
        'field_label'     => 'required|max_length[150]',
        'requested_value' => 'required|max_length[1000]',
        'justification'   => 'required|min_length[20]|max_length[1000]',
    ];

    protected $validationMessages = [
        'justification' => [
            'min_length' => 'A justificativa deve ter pelo menos 20 caracteres.',
            'required'   => 'A justificativa é obrigatória.',
        ],
        'requested_value' => [
            'required' => 'O novo valor é obrigatório.',
        ],
    ];

    public function getPendingCount(): int
    {
        return $this->where('status', 'pending')->countAllResults();
    }

    public function getPendingForEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getAllForEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getPendingWithEmployeeData(): array
    {
        return $this->db->table('employee_change_requests cr')
            ->select('cr.*, e.name AS employee_name, e.email AS employee_email, e.department,
                      r.name AS reviewer_name')
            ->join('employees e', 'e.id = cr.employee_id', 'left')
            ->join('employees r', 'r.id = cr.reviewed_by', 'left')
            ->where('cr.deleted_at IS NULL')
            ->orderBy('cr.status = \'pending\'', 'DESC')
            ->orderBy('cr.created_at', 'DESC')
            ->get()->getResultObject();
    }

    public function approve(int $id, int $reviewedBy, string $note = ''): bool
    {
        return $this->update($id, [
            'status'      => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note,
        ]);
    }

    public function reject(int $id, int $reviewedBy, string $note): bool
    {
        return $this->update($id, [
            'status'      => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note,
        ]);
    }
}

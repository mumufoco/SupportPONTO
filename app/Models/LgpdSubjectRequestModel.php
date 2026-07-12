<?php

namespace App\Models;

use CodeIgniter\Model;

class LgpdSubjectRequestModel extends Model
{
    protected $table = 'lgpd_subject_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'request_id',
        'employee_id',
        'requested_by',
        'request_type',
        'status',
        'reason',
        'resolution_notes',
        'due_at',
        'resolved_at',
    ];

    protected $validationRules = [
        'request_id' => 'required|max_length[80]',
        'employee_id' => 'required|integer',
        'request_type' => 'required|in_list[access,export,correction,anonymization,deactivation,biometric_purge]',
        'status' => 'permit_empty|in_list[pending,in_review,approved,rejected,completed,cancelled]',
    ];
}

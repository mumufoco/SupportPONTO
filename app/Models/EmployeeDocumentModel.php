<?php

namespace App\Models;

use App\Enums\DocumentType;
use CodeIgniter\Model;

class EmployeeDocumentModel extends Model
{
    protected $table            = 'employee_documents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'uploaded_by',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'employee_id' => 'required|integer',
        'document_type' => 'required|in_list[]',
        'original_filename' => 'required|max_length[255]',
        'stored_path' => 'required|max_length[255]',
    ];

    protected $validationMessages = [
        'document_type' => [
            'in_list' => 'Tipo de documento inválido.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    public function __construct()
    {
        parent::__construct();

        $this->validationRules['document_type'] = 'required|in_list[' . DocumentType::validationList() . ']';
    }

    /** Documentos ativos de um colaborador, agrupados por tipo, mais recentes primeiro. */
    public function listByEmployee(int $employeeId): array
    {
        $rows = $this->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $grouped = [];
        foreach (DocumentType::values() as $type) {
            $grouped[$type] = [];
        }

        foreach ($rows as $row) {
            $grouped[$row->document_type][] = $row;
        }

        return $grouped;
    }
}

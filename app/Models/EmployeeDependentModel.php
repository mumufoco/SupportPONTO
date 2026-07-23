<?php

namespace App\Models;

use App\Enums\DependentKinshipType;
use App\Services\Security\EncryptionService;
use CodeIgniter\Model;

class EmployeeDependentModel extends Model
{
    protected $table            = 'employee_dependents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'name',
        'cpf',
        'cpf_hash',
        'birth_date',
        'kinship_type',
        'irrf_dependent',
        'family_allowance_dependent',
        'has_disability',
        'active',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'employee_id'  => 'required|integer',
        'name'         => 'required|min_length[3]|max_length[150]',
        'cpf'          => 'required|valid_cpf',
        'birth_date'   => 'required|valid_date',
        'kinship_type' => 'required|in_list[]',
    ];

    protected $validationMessages = [
        'name' => [
            'min_length' => 'O nome deve ter no mínimo 3 caracteres.',
        ],
        'cpf' => [
            'valid_cpf' => 'CPF inválido.',
        ],
        'kinship_type' => [
            'in_list' => 'Grau de parentesco inválido.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['sanitizeCpf', 'encryptCpf'];
    protected $beforeUpdate   = ['sanitizeCpf', 'encryptCpf'];
    protected $afterFind      = ['decryptCpf', 'castBooleans'];

    public function __construct()
    {
        parent::__construct();

        $this->validationRules['kinship_type'] = 'required|in_list[' . DependentKinshipType::validationList() . ']';
    }

    /** Lista dependentes com dados do colaborador vinculado, para a tela geral de gestão. */
    public function listWithEmployee(?int $employeeId = null, ?bool $onlyActive = null): array
    {
        $builder = $this->select('employee_dependents.*, employees.name AS employee_name, employees.department AS employee_department')
            ->join('employees', 'employees.id = employee_dependents.employee_id')
            ->orderBy('employees.name', 'ASC')
            ->orderBy('employee_dependents.name', 'ASC');

        if ($employeeId !== null) {
            $builder->where('employee_dependents.employee_id', $employeeId);
        }

        if ($onlyActive !== null) {
            $builder->where('employee_dependents.active', $onlyActive);
        }

        return $builder->findAll();
    }

    public function isCpfUniqueForEmployee(int $employeeId, string $cpf, ?int $excludeId = null): bool
    {
        $hash = hash('sha256', preg_replace('/\D/', '', $cpf) ?? '');

        $builder = $this->where('employee_id', $employeeId)->where('cpf_hash', $hash);
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() === 0;
    }

    protected function sanitizeCpf(array $data): array
    {
        if (isset($data['data']['cpf'])) {
            $data['data']['cpf'] = preg_replace('/\D/', '', (string) $data['data']['cpf']);
        }

        return $data;
    }

    /** Criptografa o CPF em repouso — mesma estratégia de EmployeeModel::encryptCpf(). */
    protected function encryptCpf(array $data): array
    {
        $payload = $data['data'] ?? [];

        if (isset($payload['cpf']) && $payload['cpf'] !== '') {
            $digits = (string) $payload['cpf'];
            $payload['cpf_hash'] = hash('sha256', $digits);
            $payload['cpf'] = (new EncryptionService())->encrypt($digits);
        }

        $data['data'] = $payload;

        return $data;
    }

    /** Decripta o CPF ao ler — transparente para o resto do sistema. */
    protected function decryptCpf(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (!is_object($row) || empty($row->cpf)) {
                continue;
            }

            $row->cpf = EmployeeModel::decryptCpfValue((string) $row->cpf);
        }

        return $data;
    }

    protected function castBooleans(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $fields = ['irrf_dependent', 'family_allowance_dependent', 'has_disability', 'active'];
        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            foreach ($fields as $field) {
                if (property_exists($row, $field)) {
                    $row->{$field} = in_array($row->{$field}, [true, 't', '1', 1, 'true'], true);
                }
            }
        }

        return $data;
    }
}

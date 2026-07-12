<?php

namespace App\Models;

use App\Enums\Role;
use CodeIgniter\Model;
use Config\Database;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        // Campos básicos
        'name', 'email', 'password', 'cpf', 'unique_code', 'role', 'role_id', 'work_unit', 'work_unit_id', 'department', 'department_id', 'position', 'position_id',
        'phone', 'admission_date', 'active', 'photo_path', 'face_encoding', 'created_at', 'updated_at',
        'manager_id', 'last_login', 'birth_date',
        
        // Campos de trabalho
        // Os nomes canônicos no banco são expected_hours_daily/work_schedule_start/work_schedule_end.
        // daily_hours/work_start_time/work_end_time permanecem como aliases compatíveis e são sincronizados
        // nos callbacks do Model para evitar divergência entre schema, seeder e camada de aplicação.
        'expected_hours_daily', 'work_schedule_start', 'work_schedule_end',
        'daily_hours', 'weekly_hours', 'work_start_time', 'work_end_time', 'lunch_start_time', 'lunch_end_time',
        'work_shift_id', 'allow_remote_punch', 'require_geolocation', 'extra_hours_balance', 'owed_hours_balance',
        
        // Biométricos
        'has_face_biometric', 'has_fingerprint_biometric',
        
        // Autenticação de dois fatores
        'two_factor_enabled', 'two_factor_secret', 'two_factor_backup_codes', 'two_factor_verified_at',
        
        // Reset de senha / remember-me
        'password_reset_token', 'password_reset_expires', 'must_change_password', 'password_changed_at',
        'remember_token', 'remember_token_expires',
        
        // Campos obrigatórios da Portaria do MTE
        'rg', 'rg_orgao_emissor', 'rg_data_expedicao', 'nacionalidade', 'logradouro', 'numero', 'complemento',
        'bairro', 'municipio', 'uf', 'cep', 'telefone', 'ctps_numero', 'ctps_serie', 'ctps_uf', 'ctps_data_emissao',
        'pis', 'pis_pasep', 'cargo', 'salario_base', 'jornada_trabalho', 'setor', 'horario_entrada', 'horario_saida',
        'dependentes', 'banco', 'agencia', 'conta', 'pix_key_type', 'pix_key', 'demission_date', 'estado_civil', 'sexo', 'cor_raca',
        // QUA-05 FIX: 'is_active' removido — 'active' é o campo canônico.
        'grau_instrucao', 'tipo_contrato', 'deficiencia',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // CRIT-08 (auditoria): sem isso, delete($id) disparava um DELETE físico real, que
    // arrastava em cascata (ON DELETE CASCADE) todo o histórico legal do funcionário —
    // batidas de ponto, justificativas, advertências e templates biométricos — de forma
    // irrecuperável, violando a obrigação de retenção da Portaria MTE 671/2021. A coluna
    // deleted_at já existia na tabela desde a migração original, só nunca foi ativada
    // aqui. EmployeeStatusService::deleteEmployee()/rejectRegistration() chamam
    // delete($id) sem purge=true, então passam a fazer soft-delete automaticamente.
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        // O Model precisa aceitar inserts parciais feitos pelo instalador, seeders,
        // autocadastro e rotinas de suporte. A validação completa do cadastro de
        // colaboradores fica concentrada em EmployeeValidationRulesProvider.
        'name' => 'permit_empty|min_length[1]|max_length[255]',
        'email' => 'permit_empty|valid_email|max_length[255]',
        'password' => 'permit_empty|min_length[8]|max_length[255]',
        'cpf' => 'permit_empty|valid_cpf',
        'unique_code' => 'permit_empty|max_length[10]',
        'role' => 'permit_empty|valid_employee_role',
        'active' => 'permit_empty|in_list[true,false,0,1]',
        'work_unit_id' => 'permit_empty|integer',
        'department_id' => 'permit_empty|integer',
        'position_id' => 'permit_empty|integer',
        'work_shift_id' => 'permit_empty|integer',
        'phone' => 'permit_empty|valid_phone_br',
        'telefone' => 'permit_empty|valid_phone_br',
        'admission_date' => 'permit_empty|valid_date[Y-m-d]',
        'birth_date' => 'permit_empty|valid_date[Y-m-d]',
        'rg_data_expedicao' => 'permit_empty|valid_date[Y-m-d]',
        'ctps_data_emissao' => 'permit_empty|valid_date[Y-m-d]',
        'demission_date' => 'permit_empty|valid_date[Y-m-d]',
        'cep' => 'permit_empty|exact_length[8]|regex_match[/^\d{8}$/]',
        'pis' => 'permit_empty|max_length[20]',
        'pis_pasep' => 'permit_empty|max_length[20]',
        'salario_base' => 'permit_empty|decimal',
        'expected_hours_daily' => 'permit_empty|decimal',
        'daily_hours' => 'permit_empty|decimal',
        'weekly_hours' => 'permit_empty|decimal',
        'allow_remote_punch' => 'permit_empty|in_list[true,false,0,1]',
        'require_geolocation' => 'permit_empty|in_list[true,false,0,1]',
        'extra_hours_balance' => 'permit_empty|decimal',
        'owed_hours_balance' => 'permit_empty|decimal',
    ];

    protected $validationMessages = [
        'cpf' => [
            'valid_cpf' => 'CPF inválido.',
        ],
        'cep' => [
            'regex_match' => 'CEP deve conter 8 dígitos.',
        ],
        'telefone' => [
            'valid_phone_br' => 'Telefone inválido.',
        ],
        'pis_pasep' => [
            'regex_match' => 'PIS/PASEP deve conter 11 dígitos.',
        ],
        'salario_base' => [
            'decimal' => 'Salário base deve ser um valor decimal.',
        ],
        // Campos da Portaria MTE
        'rg' => [
            'required' => 'RG é obrigatório conforme Portaria do MTE.',
        ],
        'ctps_numero' => [
            'required' => 'Número da CTPS é obrigatório.',
        ],
        'pis_pasep' => [
            'required' => 'PIS/PASEP é obrigatório.',
        ],
    ];

    protected $skipValidation = false;

    protected $beforeInsert = ['sanitizeData', 'syncWorkScheduleAliases', 'encodeDependentes', 'syncRoleFields'];
    protected $beforeUpdate = ['sanitizeData', 'syncWorkScheduleAliases', 'encodeDependentes', 'syncRoleFields'];
    protected $afterFind = ['hydrateWorkScheduleAliases', 'castBooleans'];

    protected function sanitizeData(array $data): array
    {
        $data['data'] = $this->normalizeLegacyActivePayload($data['data'] ?? []);

        foreach (['cpf', 'telefone', 'phone', 'cep', 'pis_pasep'] as $field) {
            if (isset($data['data'][$field])) {
                $data['data'][$field] = preg_replace('/\D/', '', (string) $data['data'][$field]);
            }
        }

        if (isset($data['data']['name'])) {
            $data['data']['name'] = trim((string) $data['data']['name']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeLegacyActivePayload(array $payload): array
    {
        if (array_key_exists('is_active', $payload) && !array_key_exists('active', $payload)) {
            $payload['active'] = $payload['is_active'];
        }

        unset($payload['is_active']);

        return $payload;
    }



    protected function syncWorkScheduleAliases(array $data): array
    {
        $payload = $data['data'] ?? [];

        $canonicalToAlias = [
            'expected_hours_daily' => 'daily_hours',
            'work_schedule_start' => 'work_start_time',
            'work_schedule_end' => 'work_end_time',
        ];

        foreach ($canonicalToAlias as $canonical => $alias) {
            $canonicalExists = array_key_exists($canonical, $payload) && $payload[$canonical] !== null && $payload[$canonical] !== '';
            $aliasExists = array_key_exists($alias, $payload) && $payload[$alias] !== null && $payload[$alias] !== '';

            if ($canonicalExists && !$aliasExists) {
                $payload[$alias] = $payload[$canonical];
            }

            if ($aliasExists && !$canonicalExists) {
                $payload[$canonical] = $payload[$alias];
            }

            if ($canonicalExists && $aliasExists && (string) $payload[$canonical] !== (string) $payload[$alias]) {
                $payload[$alias] = $payload[$canonical];
            }
        }

        $payload['extra_hours_balance'] = isset($payload['extra_hours_balance']) ? (float) $payload['extra_hours_balance'] : ($payload['extra_hours_balance'] ?? 0.0);
        $payload['owed_hours_balance'] = isset($payload['owed_hours_balance']) ? (float) $payload['owed_hours_balance'] : ($payload['owed_hours_balance'] ?? 0.0);

        $data['data'] = $payload;

        return $data;
    }

    protected function hydrateWorkScheduleAliases(array $data): array
    {
        if (!array_key_exists('data', $data)) {
            return $data;
        }

        $data['data'] = $this->mapAliasesOnFind($data['data']);

        return $data;
    }

    protected function castBooleans(array $data): array
    {
        if (!array_key_exists('data', $data)) {
            return $data;
        }

        $fields = [
            'active', 'has_face_biometric', 'has_fingerprint_biometric',
            'two_factor_enabled', 'allow_remote_punch', 'require_geolocation',
            'must_change_password',
        ];

        $data['data'] = $this->applyBoolCastOnFind($data['data'], $fields);

        return $data;
    }

    private function applyBoolCastOnFind($payload, array $fields)
    {
        if (is_array($payload) && $payload !== [] && array_keys($payload) === range(0, count($payload) - 1)) {
            foreach ($payload as $index => $row) {
                $payload[$index] = $this->applyBoolCastToRow($row, $fields);
            }
            return $payload;
        }

        if (is_object($payload) || is_array($payload)) {
            return $this->applyBoolCastToRow($payload, $fields);
        }

        return $payload;
    }

    private function applyBoolCastToRow($row, array $fields)
    {
        foreach ($fields as $field) {
            $val = is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
            if ($val === null) {
                continue;
            }
            // PostgreSQL returns booleans as 't'/'f' strings
            if ($val === 't' || $val === true) {
                $cast = true;
            } elseif ($val === 'f' || $val === false || $val === '') {
                $cast = false;
            } else {
                $cast = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($cast === null) {
                    continue;
                }
            }
            if (is_array($row)) {
                $row[$field] = $cast;
            } else {
                $row->{$field} = $cast;
            }
        }
        return $row;
    }

    /**
     * @param array<int, object|array<string, mixed>>|object|array<string, mixed>|null $payload
     * @return array<int, object|array<string, mixed>>|object|array<string, mixed>|null
     */
    private function mapAliasesOnFind($payload)
    {
        if (is_array($payload)) {
            if ($payload === []) {
                return $payload;
            }

            $isList = array_keys($payload) === range(0, count($payload) - 1);
            if ($isList) {
                foreach ($payload as $index => $row) {
                    $payload[$index] = $this->applyWorkScheduleAliases($row);
                }

                return $payload;
            }

            return $this->applyWorkScheduleAliases($payload);
        }

        if (is_object($payload)) {
            return $this->applyWorkScheduleAliases($payload);
        }

        return $payload;
    }

    /**
     * @param object|array<string, mixed> $row
     * @return object|array<string, mixed>
     */
    private function applyWorkScheduleAliases($row)
    {
        $pairs = [
            'expected_hours_daily' => 'daily_hours',
            'work_schedule_start' => 'work_start_time',
            'work_schedule_end' => 'work_end_time',
        ];

        foreach ($pairs as $canonical => $alias) {
            $canonicalValue = $this->fieldValue($row, $canonical);
            $aliasValue = $this->fieldValue($row, $alias);

            if (($canonicalValue === null || $canonicalValue === '') && $aliasValue !== null && $aliasValue !== '') {
                $row = $this->setFieldValue($row, $canonical, $aliasValue);
                $canonicalValue = $aliasValue;
            }

            if ($canonicalValue !== null && $canonicalValue !== '') {
                $row = $this->setFieldValue($row, $alias, $canonicalValue);
            }
        }

        foreach (['extra_hours_balance', 'owed_hours_balance'] as $field) {
            $value = $this->fieldValue($row, $field);
            if ($value === null || $value === '') {
                $row = $this->setFieldValue($row, $field, 0.0);
            }
        }

        return $row;
    }

    /**
     * @param object|array<string, mixed> $row
     */
    private function fieldValue($row, string $field)
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        return $row->{$field} ?? null;
    }

    /**
     * @param object|array<string, mixed> $row
     * @param mixed $value
     * @return object|array<string, mixed>
     */
    private function setFieldValue($row, string $field, $value)
    {
        if (is_array($row)) {
            $row[$field] = $value;
            return $row;
        }

        $row->{$field} = $value;
        return $row;
    }

    protected function encodeDependentes(array $data): array
    {
        if (isset($data['data']['dependentes']) && is_array($data['data']['dependentes'])) {
            $data['data']['dependentes'] = json_encode($data['data']['dependentes']);
        }

        return $data;
    }

    public function findByEmail(string $email)
    {
        return $this->asObject()
            ->where('LOWER(email)', $this->normalizeEmail($email))
            ->first();
    }

    public function findByUniqueCode(string $code)
    {
        return $this->asObject()->where('unique_code', trim($code))->first();
    }

    public function findByCode(string $code)
    {
        return $this->findByUniqueCode($code);
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public function isEmailUnique(string $email, ?int $excludeId = null): bool
    {
        return $this->buildUniquenessQuery('LOWER(email)', $this->normalizeEmail($email), $excludeId)->countAllResults() === 0;
    }

    public function isCpfUnique(string $cpf, ?int $excludeId = null): bool
    {
        return $this->buildUniquenessQuery('cpf', $this->normalizeDigits($cpf), $excludeId)->countAllResults() === 0;
    }

    public function getActive(): array
    {
        return $this->where('active', true)->findAll();
    }

    public function getActiveEmployees(): array
    {
        return $this->getActive();
    }

    public function getByRole(string $role): array
    {
        return $this->where('role', $role)->where('active', true)->findAll();
    }

    /**
     * @param list<int|string|null> $ids
     * @return array<int, string>
     */
    public function getNamesByIds(array $ids): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn($id) => is_numeric($id) ? (int) $id : null,
            $ids
        ))));

        if ($normalizedIds === []) {
            return [];
        }

        $employees = $this->select('id, name')
            ->whereIn('id', $normalizedIds)
            ->findAll();

        $names = [];
        foreach ($employees as $employee) {
            $names[(int) $employee->id] = (string) $employee->name;
        }

        return $names;
    }

    public function getPositionsByDepartment(int $departmentId): array
    {
        try {
            $results = Database::connect()
                ->table('positions')
                ->where('department_id', $departmentId)
                ->where('active', true)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'EmployeeModel::getPositionsByDepartment error: ' . $e->getMessage());
            return [];
        }

        return array_map(static fn(array $result): array => [
            'id' => $result['id'],
            'name' => $result['name'],
        ], $results);
    }

    public function decodeDependentes(object $employee): object
    {
        $employee->dependentes = ! empty($employee->dependentes) && is_string($employee->dependentes)
            ? (json_decode($employee->dependentes, true) ?: [])
            : [];

        return $employee;
    }


    protected function syncRoleFields(array $data): array
    {
        $payload = $data['data'] ?? [];

        if (!empty($payload['role_id']) && empty($payload['role'])) {
            try {
                $row = $this->db->table('roles')->select('name')->where('id', (int) $payload['role_id'])->get()->getRowArray();
                if ($row) {
                    $payload['role'] = $row['name'];
                }
            } catch (\Throwable) {}
        }

        if (!empty($payload['role']) && empty($payload['role_id'])) {
            try {
                $row = $this->db->table('roles')->select('id')->where('LOWER(name)', strtolower($payload['role']))->get()->getRowArray();
                if ($row) {
                    $payload['role_id'] = (int) $row['id'];
                }
            } catch (\Throwable) {}
        }

        $data['data'] = $payload;
        return $data;
    }


    
    public function createAdmin(array $data)
    {
        if (empty($data['email']) || empty($data['password'])) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $insert = [
            'name' => $data['name'] ?? 'Administrator',
            'email' => trim((string) $data['email']),
            'password' => password_hash((string) $data['password'], PASSWORD_ARGON2ID),
            'role' => $data['role'] ?? 'admin',
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
            'unique_code' => $data['unique_code'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $this->insert($insert);

            return $this->getInsertID();
        } catch (\Throwable $e) {
            log_message('error', 'EmployeeModel::createAdmin error: ' . $e->getMessage());

            return false;
        }
    }

    public function setLastLogin(int $id, ?string $datetime = null): bool
    {
        try {
            if (! $this->db->fieldExists('last_login', $this->table)) {
                log_message('warning', "EmployeeModel::setLastLogin - 'last_login' column does not exist in {$this->table} table");
                return false;
            }

            $result = $this->update($id, ['last_login' => $datetime ?? date('Y-m-d H:i:s')]);

            if (! $result) {
                log_message('warning', "EmployeeModel::setLastLogin - Failed to update last_login for employee ID {$id}");
                return false;
            }

            log_message('debug', "EmployeeModel::setLastLogin - Updated last_login for employee ID {$id}");
            return true;
        } catch (\Throwable $e) {
            log_message('error', "EmployeeModel::setLastLogin - Exception for employee ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function isValidCpf(string $cpf): bool
    {
        $cpf = $this->normalizeDigits($cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        $digit1 = $this->calculateCpfDigit($cpf, 10);
        $digit2 = $this->calculateCpfDigit($cpf, 11);

        return $digit1 === (int) $cpf[9] && $digit2 === (int) $cpf[10];
    }

    public function findByCpf(string $cpf): ?object
    {
        return $this->where('cpf', $this->normalizeDigits($cpf))->first();
    }

    protected function buildUniquenessQuery(string $field, string $value, ?int $excludeId = null)
    {
        $query = $this->where($field, $value);

        if ($excludeId !== null) {
            $query->where('id !=', $excludeId);
        }

        return $query;
    }

    protected function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    protected function normalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    protected function calculateCpfDigit(string $cpf, int $factorStart): int
    {
        $sum = 0;
        $limit = $factorStart - 1;

        for ($i = 0; $i < $limit; $i++) {
            $sum += (int) $cpf[$i] * ($factorStart - $i);
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}

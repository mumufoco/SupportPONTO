<?php

namespace App\Services\Auth;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\NotificationModel;
use App\Models\UserConsentModel;

class RegisterWorkflowService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly UserConsentModel $consentModel = new UserConsentModel(),
        private readonly NotificationModel $notificationModel = new NotificationModel(),
        private readonly RegisterPolicyService $policyService = new RegisterPolicyService(),
    ) {
    }

    public function positionsByDepartment(?string $departmentId): array
    {
        if (empty($departmentId)) {
            return [];
        }

        $db = \Config\Database::connect();

        return $db->table('positions')
            ->where('department_id', $departmentId)
            ->where('active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function registerSelf(array $postData, string $clientIp): array
    {
        $data = $this->buildSelfRegistrationData($postData);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $employeeId = $this->employeeModel->insert($data);
            if (!$employeeId) {
                throw new \RuntimeException('Erro ao criar funcionário.');
            }

            $this->recordConsents((int) $employeeId, $clientIp);

            $this->auditModel->log(
                null,
                'EMPLOYEE_SELF_REGISTERED',
                'employees',
                (int) $employeeId,
                null,
                ['name' => $data['name'], 'email' => $data['email']],
                "Auto-cadastro: {$data['name']} ({$data['email']})",
                'info'
            );

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Erro na transação.');
            }

            $this->notifyAdmins((int) $employeeId);

            return ['success' => true, 'employee_id' => (int) $employeeId];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Registration error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erro ao realizar cadastro. Tente novamente.'];
        }
    }

    public function registerByManager(array $postData, int $actorId): array
    {
        $data = $this->buildManagerRegistrationData($postData);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $employeeId = $this->employeeModel->insert($data);
            if (!$employeeId) {
                throw new \RuntimeException('Erro ao criar funcionário.');
            }

            $this->auditModel->log(
                $actorId,
                'EMPLOYEE_CREATED',
                'employees',
                (int) $employeeId,
                null,
                $data,
                "Funcionário criado por gestor: {$data['name']} ({$data['email']})",
                'info'
            );

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Erro na transação.');
            }

            return ['success' => true, 'employee_id' => (int) $employeeId];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Employee creation error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erro ao cadastrar funcionário. Tente novamente.'];
        }
    }

    private function buildSelfRegistrationData(array $postData): array
    {
        return [
            'name' => $postData['name'] ?? null,
            'birth_date' => $postData['birth_date'] ?? null,
            'cpf' => $this->policyService->cleanCPF((string) ($postData['cpf'] ?? '')),
            'rg' => $postData['rg'] ?? null,
            'rg_orgao_emissor' => $postData['rg_orgao_emissor'] ?? null,
            'rg_data_expedicao' => $postData['rg_data_expedicao'] ?? null,
            'estado_civil' => $postData['estado_civil'] ?? null,
            'nacionalidade' => $postData['nacionalidade'] ?? null,
            'sexo' => $postData['sexo'] ?? null,
            'cor_raca' => $postData['cor_raca'] ?? null,
            'grau_instrucao' => $postData['grau_instrucao'] ?? null,
            'logradouro' => $postData['logradouro'] ?? null,
            'numero' => $postData['numero'] ?? null,
            'complemento' => $postData['complemento'] ?? null,
            'bairro' => $postData['bairro'] ?? null,
            'municipio' => $postData['municipio'] ?? null,
            'uf' => $postData['uf'] ?? null,
            'cep' => $postData['cep'] ?? null,
            'telefone' => $postData['telefone'] ?? null,
            'email' => $postData['email'] ?? null,
            'possui_ctps_fisica' => in_array($postData['possui_ctps_fisica'] ?? null, [true, 1, '1', 'true', 'on'], true),
            'ctps_numero' => $postData['ctps_numero'] ?? null,
            'ctps_serie' => $postData['ctps_serie'] ?? null,
            'ctps_uf' => $postData['ctps_uf'] ?? null,
            'ctps_data_emissao' => $postData['ctps_data_emissao'] ?? null,
            'pis_pasep' => $postData['pis_pasep'] ?? null,
            'admission_date' => $postData['admission_date'] ?? null,
            'cargo' => $postData['cargo'] ?? null,
            'salario_base' => $postData['salario_base'] ?? null,
            'jornada_trabalho' => $postData['jornada_trabalho'] ?? null,
            'tipo_contrato' => $postData['tipo_contrato'] ?? null,
            'setor' => $postData['setor'] ?? null,
            'horario_entrada' => $postData['horario_entrada'] ?? null,
            'horario_saida' => $postData['horario_saida'] ?? null,
            'dependentes' => $this->buildDependentes($postData),
            'banco' => $postData['banco'] ?? null,
            'agencia' => $postData['agencia'] ?? null,
            'conta' => $postData['conta'] ?? null,
            'deficiencia' => $postData['deficiencia'] ?? null,
            'demission_date' => $postData['demission_date'] ?? null,
            'password' => password_hash((string) ($postData['password'] ?? ''), PASSWORD_ARGON2ID),
            'unique_code' => !empty($postData['unique_code']) ? $postData['unique_code'] : $this->generateUniqueCode(),
            'work_unit' => $this->resolveCatalogName('work_units', $postData['work_unit'] ?? null),
            'department' => $this->resolveCatalogName('departments', $postData['department'] ?? null),
            'position' => $this->resolveCatalogName('positions', $postData['position'] ?? null),
            'allow_remote_punch' => ($postData['allow_remote_punch'] ?? '0') === '1',
            'require_geolocation' => ($postData['require_geolocation'] ?? '0') === '1',
            'role' => 'funcionario',
            'active' => false,
            'must_change_password' => false,
        ];
    }

    private function resolveCatalogName(string $table, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stringValue = trim((string) $value);
        if (!ctype_digit($stringValue)) {
            return $stringValue;
        }

        $row = \Config\Database::connect()
            ->table($table)
            ->select('name')
            ->where('id', (int) $stringValue)
            ->where('active', true)
            ->get()
            ->getRowArray();

        return $row['name'] ?? $stringValue;
    }

    private function buildManagerRegistrationData(array $postData): array
    {
        return [
            'name' => $postData['name'] ?? null,
            'email' => $postData['email'] ?? null,
            'cpf' => $this->policyService->cleanCPF((string) ($postData['cpf'] ?? '')),
            'password' => (string) ($postData['password'] ?? ''),
            'role' => $postData['role'] ?? 'funcionario',
            'department' => $postData['department'] ?? null,
            'position' => $postData['position'] ?? null,
            'phone' => $postData['phone'] ?? null,
            'admission_date' => $postData['admission_date'] ?? null,
            'daily_hours' => $postData['daily_hours'] ?? 8.00,
            'weekly_hours' => $postData['weekly_hours'] ?? 44.00,
            'work_start_time' => $postData['work_start_time'] ?? '08:00:00',
            'work_end_time' => $postData['work_end_time'] ?? '18:00:00',
            'lunch_start_time' => $postData['lunch_start_time'] ?? '12:00:00',
            'lunch_end_time' => $postData['lunch_end_time'] ?? '13:00:00',
            // Nunca ativo direto: managerRules() não exige boa parte dos campos
            // obrigatórios do MTE/eSocial (CPF/PIS já bastam para o cadastro rápido,
            // mas não para ativar). Fica pendente até passar por
            // EmployeeStatusService::approveRegistration(), que bloqueia a ativação
            // enquanto faltar dado obrigatório — mesma regra de todo outro fluxo de
            // admissão.
            'active' => false,
        ];
    }

    private function recordConsents(int $employeeId, string $clientIp): void
    {
        $now = date('Y-m-d H:i:s');
        $consents = [
            ['consent_type' => 'biometric_data', 'purpose' => 'Registro de ponto eletrônico através de reconhecimento facial e biometria', 'legal_basis' => 'Consentimento (Art. 7º, I da LGPD)', 'consent_text' => 'Autorizo o tratamento de meus dados biométricos (facial e digital) para fins de registro de ponto eletrônico.'],
            ['consent_type' => 'personal_data', 'purpose' => 'Gerenciamento de jornada de trabalho e cumprimento de obrigações trabalhistas', 'legal_basis' => 'Cumprimento de obrigação legal (Art. 7º, II da LGPD)', 'consent_text' => 'Autorizo o tratamento de meus dados pessoais para fins trabalhistas conforme CLT Art. 74.'],
            ['consent_type' => 'geolocation', 'purpose' => 'Validação de local de registro de ponto', 'legal_basis' => 'Consentimento (Art. 7º, I da LGPD)', 'consent_text' => 'Autorizo o uso da minha localização para validação do registro de ponto.'],
        ];

        foreach ($consents as $consent) {
            $this->consentModel->insert([
                'employee_id' => $employeeId,
                'consent_type' => $consent['consent_type'],
                'purpose' => $consent['purpose'],
                'legal_basis' => $consent['legal_basis'],
                'granted' => true,
                'granted_at' => $now,
                'ip_address' => $clientIp,
                'consent_text' => $consent['consent_text'],
                'version' => '1.0',
            ]);
        }
    }

    private function notifyAdmins(int $employeeId): void
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee || !is_object($employee)) {
            return;
        }

        $admins = $this->employeeModel->where('role', 'admin')->where('active', true)->findAll();
        foreach ($admins as $admin) {
            $this->notificationModel->insert([
                'user_id' => $admin->id,
                'title' => 'Novo cadastro pendente de aprovação',
                'message' => "O funcionário {$employee->name} ({$employee->email}) solicitou cadastro no sistema.",
                'type' => 'employee_registration',
                'link' => site_url('employees/pending'),
                'read' => false,
            ]);
        }
    }

    private function buildDependentes(array $postData): string
    {
        $dependentes = [];
        $nomes = $postData['dependente_nome'] ?? [];
        $cpfs = $postData['dependente_cpf'] ?? [];
        $datas = $postData['dependente_data'] ?? [];
        $parentescos = $postData['dependente_parentesco'] ?? [];

        foreach ($nomes as $key => $nome) {
            $dependentes[] = [
                'nome' => $nome,
                'cpf' => $cpfs[$key] ?? null,
                'data_nascimento' => $datas[$key] ?? null,
                'parentesco' => $parentescos[$key] ?? null,
            ];
        }

        return json_encode($dependentes);
    }

    private function generateUniqueCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $code = '';
            for ($i = 0; $i < 9; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }

            $exists = $this->employeeModel->where('unique_code', $code)->first();
        } while ($exists);

        return $code;
    }
}

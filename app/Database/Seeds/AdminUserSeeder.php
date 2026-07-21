<?php

namespace App\Database\Seeds;

use App\Models\EmployeeModel;
use App\Services\Audit\CanonicalAuditLogger;
use App\Support\InitialAdminPolicy;
use CodeIgniter\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    private function shouldPrintBootstrapPassword(): bool
    {
        $flag = strtolower(trim((string) env('ADMIN_INITIAL_PASSWORD_OUTPUT', '')));
        return in_array($flag, ['stdout', 'console', 'unsafe'], true);
    }

    private function removeLegacyBootstrapCredentials(): void
    {
        foreach ([
            WRITEPATH . 'secrets/admin_bootstrap_credentials.json',
            WRITEPATH . 'secrets/admin_bootstrap_credentials.txt',
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function run()
    {
        $adminEmail = InitialAdminPolicy::normalizeEmail((string) env('ADMIN_INITIAL_EMAIL', ''));

        if ($adminEmail === '') {
            echo "⚠️  ADMIN_INITIAL_EMAIL não configurado. Nenhum administrador inicial foi criado.\n";
            echo "   Defina ADMIN_INITIAL_EMAIL no ambiente ou use o instalador web para criar o admin inicial.\n";
            return;
        }

        $adminCpf = InitialAdminPolicy::formatCpf(trim((string) env('ADMIN_INITIAL_CPF', '')));

        if ($adminCpf === '') {
            echo "⚠️  ADMIN_INITIAL_CPF não configurado. Nenhum administrador inicial foi criado.\n";
            echo "   Defina ADMIN_INITIAL_CPF no ambiente ou use o instalador web para criar o admin inicial.\n";
            return;
        }

        $temporaryPassword = trim((string) env('ADMIN_INITIAL_PASSWORD', ''));
        $passwordWasGenerated = false;
        if ($temporaryPassword === '') {
            $temporaryPassword = InitialAdminPolicy::generateBootstrapPassword();
            $passwordWasGenerated = true;
        } else {
            $passwordErrors = InitialAdminPolicy::validateBootstrapPassword($temporaryPassword);
            if ($passwordErrors !== []) {
                echo "❌ ADMIN_INITIAL_PASSWORD não atende a política de segurança do admin inicial:\n";
                foreach ($passwordErrors as $error) {
                    echo "   - {$error}\n";
                }
                return;
            }
        }

        // Check if admin already exists
        $db = \Config\Database::connect();
        $builder = $db->table('employees');
        $employeeModel = model(EmployeeModel::class);

        // MED-11 (auditoria): cpf agora guarda o valor criptografado, não pesquisável
        // diretamente — busca por cpf_hash via EmployeeModel::findByCpf() em vez de
        // comparar o valor de cpf bruto na query builder.
        $existingAdmin = $builder->where('LOWER(email)', $adminEmail)->get()->getRow()
            ?? $employeeModel->findByCpf($adminCpf);

        if ($existingAdmin) {
            echo "Admin user already exists. Skipping...\n";
            return;
        }

        // Generate unique code (8 characters)
        $uniqueCode = strtoupper(bin2hex(random_bytes(4)));

        $passwordState = InitialAdminPolicy::initialPasswordState();

        $data = [
            'name'                  => env('ADMIN_INITIAL_NAME', 'Administrador do Sistema'),
            'email'                 => $adminEmail,
            'password'              => InitialAdminPolicy::hashBootstrapPassword($temporaryPassword),
            'must_change_password'  => $passwordState['must_change_password'],
            'password_changed_at'   => $passwordState['password_changed_at'],
            // QUA-01 FIX: CPF real via variável de ambiente.
            // ADMIN_INITIAL_CPF deve estar no .env no formato 000.000.000-00
            // Se não configurado, bloqueia o seeder com erro explícito.
            'cpf'                   => $adminCpf,
            'unique_code'           => $uniqueCode,
            'role'                  => 'admin',
            'department'            => 'Administração',
            'position'              => 'Administrador de Sistema',
            // Apenas os campos canônicos são gravados aqui: o EmployeeModel::syncWorkScheduleAliases
            // (callback beforeInsert) sincroniza automaticamente os respectivos aliases legados
            // de carga horária e horário de turno a partir destes valores canônicos,
            // evitando duplicação manual e divergência entre canônico e alias.
            'expected_hours_daily'  => 8.00,
            'work_schedule_start'   => '08:00:00',
            'work_schedule_end'     => '18:00:00',
            // string, nao bool nativo: a regra de validacao 'in_list[true,false,0,1]'
            // do EmployeeModel compara em modo estrito -- bool(true) nunca bate com
            // as strings da lista (confirmado em producao: "active field must be one
            // of..."). Mesma convencao ja usada em toda a aplicacao (ex.:
            // DepartmentCatalogService::create()).
            'active'                => 'true',
            'extra_hours_balance'   => 0.00,
            'owed_hours_balance'    => 0.00,
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ];

        // Sem skipValidation: o EmployeeModel já declara todas as suas regras como
        // 'permit_empty' justamente para aceitar inserts parciais de seeders/instalador
        // (ver comentário em EmployeeModel::$validationRules). Os dados deste seeder
        // (email, cpf, role, hash de senha, etc.) já passam pela validação padrão.
        //
        // O retorno de insert() precisa ser checado -- antes disso o codigo seguia
        // direto pra "admin criado com sucesso" mesmo se o insert tivesse falhado
        // silenciosamente (DBDebug fica desligado em producao, entao uma falha real
        // de banco nao aparece no console, so nos logs).
        $adminId = $employeeModel->insert($data);
        if ($adminId === false) {
            $errors = $employeeModel->errors();
            echo "❌ Falha ao criar o admin inicial:\n";
            foreach ($errors as $field => $message) {
                echo "   - {$field}: {$message}\n";
            }
            return;
        }

        $this->removeLegacyBootstrapCredentials();

        echo "✅ Admin user created successfully!
";
        echo "   Email: {$adminEmail}
";
        echo "   Unique Code: {$uniqueCode}
";
        echo "   ⚠️  IMPORTANT: The administrator must change the password on first login.
";
        echo "   Bootstrap password is not persisted by the seeder. Capture it only from the active installer result or provide it through the process environment.
";
        if ($this->shouldPrintBootstrapPassword()) {
            echo "   Temporary Password (unsafe stdout override): {$temporaryPassword}
";
        }
        if ($passwordWasGenerated) {
            echo "   ℹ️  This password was generated automatically for this process only because ADMIN_INITIAL_PASSWORD was not provided.
";
        }

        // Create initial consent for admin (data processing)
        //
        // $adminId ja vem de insert() acima -- nao usar $db->insertID() aqui:
        // essa chamada direta na conexao bruta cai no fallback "SELECT LASTVAL()"
        // do driver Postgres do CI4, que falha com "lastval is not yet defined
        // in this session" nesse contexto (confirmado em producao). insert() do
        // Model ja retorna o id de forma confiavel (mesmo caminho usado em toda
        // a aplicacao pra pegar o id de um registro recem-criado).

        $consentData = [
            'employee_id'   => $adminId,
            'consent_type'  => 'data_processing',
            'purpose'       => 'Administração do sistema de ponto eletrônico e processamento de dados de funcionários',
            'legal_basis'   => 'LGPD Art. 7º, V - execução de contrato',
            'granted'       => true,
            'granted_at'    => date('Y-m-d H:i:s'),
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'System Seeder',
            'consent_text'  => 'Ao utilizar o sistema de ponto eletrônico como administrador, você concorda com o processamento de dados necessários para a gestão do sistema.',
            'version'       => '1.0',
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $db->table('user_consents')->insert($consentData);

        // Log in audit through the canonical chain-aware path
        $auditLogger = new CanonicalAuditLogger();
        $auditLogger->logEntityEvent(
            $adminId,
            'CREATE',
            'employees',
            $adminId,
            null,
            [
                'name' => $data['name'],
                'email' => $adminEmail,
                'role' => 'admin',
                'must_change_password' => $passwordState['must_change_password'],
            ],
            'Admin user created via database seeder',
            'info',
        );

        echo "✅ Admin consent and audit log created\n";
    }

}

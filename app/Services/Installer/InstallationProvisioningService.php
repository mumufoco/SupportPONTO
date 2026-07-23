<?php

namespace App\Services\Installer;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Audit\CanonicalAuditLogger;
use App\Support\InitialAdminPolicy;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

class InstallationProvisioningService
{
    private BaseConnection $db;

    /**
     * @var array<string, array{critical: bool, label: string}>
     */
    private array $seeders = [
        'AuthGroupsSeeder' => [
            'critical' => true,
            'label' => 'grupos e permissões do Shield',
        ],
        'SettingsSeeder' => [
            'critical' => true,
            'label' => 'configurações gerais do sistema',
        ],
        'BiometricSettingsSeeder' => [
            'critical' => true,
            'label' => 'configurações biométricas padrão',
        ],
        'WorkShiftSeeder' => [
            'critical' => false,
            'label' => 'turnos iniciais de trabalho',
        ],
        'GeofenceSeeder' => [
            'critical' => false,
            'label' => 'geofences de exemplo',
        ],
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param array{name:string,email:string,cpf:string,password_plain:string}|null $admin
     * @return array<string, mixed>
     */
    public function provision(?array $admin = null): array
    {
        $summary = [
            'admin' => 'skipped',
            'seeders' => [],
            'warnings' => [],
            'blockers' => [],
        ];

        if ($admin !== null) {
            $summary['admin'] = $this->createOrUpdateInitialAdmin($admin);
        }

        foreach ($this->seeders as $seeder => $definition) {
            $summary['seeders'][$seeder] = $this->runSeeder(
                $seeder,
                (bool) ($definition['critical'] ?? false),
                (string) ($definition['label'] ?? $seeder),
                $summary['warnings'],
                $summary['blockers'],
            );
        }

        if ($summary['blockers'] !== []) {
            throw new RuntimeException(
                'O provisionamento inicial foi interrompido porque seeders obrigatórios falharam: '
                . implode('; ', $summary['blockers'])
            );
        }

        return $summary;
    }

    /**
     * @param array{name:string,email:string,cpf:string,password_plain:string} $admin
     */
    public function createOrUpdateInitialAdmin(array $admin): string
    {
        $employeeModel = model(EmployeeModel::class);
        $email = InitialAdminPolicy::normalizeEmail((string) ($admin['email'] ?? ''));
        $cpf = InitialAdminPolicy::formatCpf((string) ($admin['cpf'] ?? ''));

        if ($email === '' || preg_replace('/\D/', '', $cpf) === '') {
            throw new RuntimeException('Dados do administrador inicial estão incompletos para o provisionamento.');
        }

        $passwordErrors = InitialAdminPolicy::validateBootstrapPassword((string) ($admin['password_plain'] ?? ''));
        if ($passwordErrors !== []) {
            throw new RuntimeException('A senha bootstrap do administrador inicial não atende à política de segurança.');
        }

        $now = date('Y-m-d H:i:s');
        $passwordState = InitialAdminPolicy::initialPasswordState();
        $existing = $employeeModel->findByEmail($email);
        if ($existing === null) {
            // MED-11 (auditoria): cpf agora guarda o valor criptografado, não
            // pesquisável diretamente — busca por cpf_hash via findByCpf().
            $existing = $employeeModel->findByCpf($cpf);
        }
        $uniqueCode = $existing->unique_code ?? strtoupper(bin2hex(random_bytes(4)));

        $payload = [
            'name' => trim((string) ($admin['name'] ?? 'Administrador do Sistema')),
            'email' => $email,
            'password' => InitialAdminPolicy::hashBootstrapPassword((string) $admin['password_plain']),
            'cpf' => $cpf,
            'unique_code' => $uniqueCode,
            'role' => 'admin',
            'department' => 'Administração',
            'position' => 'Administrador de Sistema',
            'active' => true,
            'must_change_password' => $passwordState['must_change_password'],
            'password_changed_at' => $passwordState['password_changed_at'],
            'updated_at' => $now,
        ];

        $this->db->transStart();

        if ($existing) {
            $employeeModel->update((int) $existing->id, $payload);
            $adminId = (int) $existing->id;
            $result = 'updated';
        } else {
            $payload['created_at'] = $now;
            $employeeModel->insert($payload);
            $adminId = (int) $employeeModel->getInsertID();
            $result = 'created';
        }

        $this->ensureAdminConsent($adminId, $now);
        $this->ensureAdminAuditLog($adminId, $payload, $result, $now);

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Falha ao consolidar o administrador inicial em transação.');
        }

        return $result;
    }

    /**
     * @param array<int, string> $warnings
     * @param array<int, string> $blockers
     */
    private function runSeeder(string $seeder, bool $critical, string $label, array &$warnings, array &$blockers): string
    {
        try {
            $runner = Database::seeder();
            $runner->call($seeder);

            return 'ok';
        } catch (Throwable $e) {
            $message = sprintf('%s (%s): %s', $seeder, $label, $e->getMessage());

            if ($critical) {
                $blockers[] = $message;
                log_message('error', 'Seeder crítico falhou durante o provisionamento inicial: {message}', ['message' => $message]);

                return 'blocker';
            }

            $warnings[] = $message;
            log_message('warning', 'Seeder opcional falhou durante o provisionamento inicial: {message}', ['message' => $message]);

            return 'warning';
        }
    }

    private function ensureAdminConsent(int $adminId, string $now): void
    {
        if (! $this->db->tableExists('user_consents')) {
            return;
        }

        $builder = $this->db->table('user_consents');
        $existing = $builder
            ->where('employee_id', $adminId)
            ->where('consent_type', 'data_processing')
            ->get()
            ->getRow();

        if ($existing !== null) {
            return;
        }

        $builder->insert([
            'employee_id' => $adminId,
            'consent_type' => 'data_processing',
            'purpose' => 'Administração do sistema de ponto eletrônico e processamento de dados de colaboradores',
            'legal_basis' => 'LGPD Art. 7º, V - execução de contrato',
            'granted' => true,
            'granted_at' => $now,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'InstallationProvisioningService',
            'consent_text' => 'Ao utilizar o sistema como administrador, você concorda com o processamento de dados necessários para a gestão do sistema.',
            'version' => '1.0',
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureAdminAuditLog(int $adminId, array $payload, string $action, string $now): void
    {
        if (! $this->db->tableExists('audit_logs')) {
            return;
        }

        $auditModel = new AuditModel();
        $existing = $auditModel
            ->groupStart()
                ->where('table_name', 'employees')
                ->orWhere('entity_type', 'employees')
            ->groupEnd()
            ->groupStart()
                ->where('record_id', $adminId)
                ->orWhere('entity_id', $adminId)
            ->groupEnd()
            ->where('description', 'Admin user provisioned via installer')
            ->first();

        if ($existing !== null) {
            return;
        }

        $auditLogger = new CanonicalAuditLogger($auditModel);
        $auditLogger->logEntityEvent(
            $adminId,
            strtoupper($action === 'created' ? 'CREATE' : 'UPDATE'),
            'employees',
            $adminId,
            null,
            [
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
                'role' => 'admin',
                'must_change_password' => $payload['must_change_password'] ?? true,
            ],
            'Admin user provisioned via installer',
            'info',
        );
    }
}

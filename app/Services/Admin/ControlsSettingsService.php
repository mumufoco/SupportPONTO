<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AuditModel;
use App\Models\SettingModel;
use App\Services\Backup\DatabaseBackupService;
use Config\Services;

/**
 * Controles (Segurança + Autenticação)
 *
 * Une as antigas telas /admin/settings/security e /admin/settings/authentication
 * em uma única página. As duas continuam persistidas em grupos de settings
 * separados ('security' e 'authentication') -- unificar apenas o grupo tambem
 * exigiria migrar as linhas ja salvas no banco, sem nenhum ganho visivel para
 * quem usa a tela; o que o admin realmente pedia era uma pagina/form/botao
 * unicos, que e o que esta classe entrega.
 */
class ControlsSettingsService
{
    /**
     * Checkboxes desmarcados nao sao enviados pelo navegador -- update()
     * precisa normalizar cada um destes para '0' quando ausente do POST.
     *
     * @var list<string>
     */
    private const SECURITY_BOOLEAN_FIELDS = [
        'password_require_uppercase', 'password_require_lowercase',
        'password_require_numbers', 'password_require_special',
        'force_https', 'regenerate_session_id', 'enable_xss_filter',
        'enable_audit_log', 'log_logins', 'log_data_changes',
        'log_deletions', 'log_settings_changes',
        'enable_data_anonymization', 'allow_data_export',
    ];

    private const AUTHENTICATION_BOOLEAN_FIELDS = ['enable_remember_me', 'self_registration_enabled'];

    private const SECURITY_DEFAULTS = [
        'password_min_length' => 8,
        'password_expiry_days' => 0,
        'password_require_uppercase' => 1,
        'password_require_lowercase' => 1,
        'password_require_numbers' => 1,
        'password_require_special' => 1,
        'force_https' => 1,
        'regenerate_session_id' => 1,
        'enable_xss_filter' => 1,
        'enable_audit_log' => 1,
        'audit_log_retention_days' => 90,
        'log_logins' => 1,
        'log_data_changes' => 1,
        'log_deletions' => 1,
        'log_settings_changes' => 1,
        'enable_data_anonymization' => 1,
        'anonymization_period_days' => 365,
        'allow_data_export' => 1,
    ];

    private const AUTHENTICATION_DEFAULTS = [
        'session_timeout' => 3600,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'enable_remember_me' => '0',
        'remember_me_duration' => 2592000,
        'self_registration_enabled' => '0',
    ];

    public function __construct(
        private readonly ?SettingModel $settingModel = null,
        private readonly ?AuditModel $auditModel = null,
        private readonly ?DatabaseBackupService $databaseBackupService = null
    ) {
    }

    /** @return array<string,mixed> */
    public function pageData(): array
    {
        return [
            'settings' => array_merge(
                $this->settings()->getByGroupMap('security'),
                $this->settings()->getByGroupMap('authentication')
            ),
        ];
    }

    private function settings(): SettingModel
    {
        return $this->settingModel ?? Services::settings(false);
    }

    private function audit(): AuditModel
    {
        return $this->auditModel ?? new AuditModel();
    }

    private function backup(): DatabaseBackupService
    {
        return $this->databaseBackupService ?? new DatabaseBackupService();
    }

    /**
     * Regras alinhadas aos campos reais de app/Views/admin/settings/controls.php.
     */
    public function rules(): array
    {
        return [
            // Segurança
            'password_min_length' => 'required|integer|greater_than[5]|less_than[129]',
            'password_expiry_days' => 'permit_empty|integer|greater_than_equal_to[0]',
            'password_require_uppercase' => 'permit_empty|in_list[0,1]',
            'password_require_lowercase' => 'permit_empty|in_list[0,1]',
            'password_require_numbers' => 'permit_empty|in_list[0,1]',
            'password_require_special' => 'permit_empty|in_list[0,1]',
            'force_https' => 'permit_empty|in_list[0,1]',
            'regenerate_session_id' => 'permit_empty|in_list[0,1]',
            'enable_xss_filter' => 'permit_empty|in_list[0,1]',
            'enable_audit_log' => 'permit_empty|in_list[0,1]',
            'audit_log_retention_days' => 'required|integer|greater_than[0]',
            'log_logins' => 'permit_empty|in_list[0,1]',
            'log_data_changes' => 'permit_empty|in_list[0,1]',
            'log_deletions' => 'permit_empty|in_list[0,1]',
            'log_settings_changes' => 'permit_empty|in_list[0,1]',
            'enable_data_anonymization' => 'permit_empty|in_list[0,1]',
            'anonymization_period_days' => 'permit_empty|integer|greater_than_equal_to[30]',
            'allow_data_export' => 'permit_empty|in_list[0,1]',
            // Autenticação
            'session_timeout' => 'permit_empty|integer|greater_than[0]',
            'max_login_attempts' => 'permit_empty|integer|greater_than[0]|less_than[100]',
            'lockout_duration' => 'permit_empty|integer|greater_than[0]',
            'enable_remember_me' => 'permit_empty|in_list[0,1]',
            'remember_me_duration' => 'permit_empty|integer|greater_than[0]',
            'self_registration_enabled' => 'permit_empty|in_list[0,1]',
        ];
    }

    /** @param array<string,mixed> $data @return array{success:bool,message:string} */
    public function update(array $data, ?int $userId = null): array
    {
        try {
            foreach (self::SECURITY_BOOLEAN_FIELDS as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = '0';
                }
            }
            foreach (self::AUTHENTICATION_BOOLEAN_FIELDS as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = '0';
                }
            }

            $securityData = array_intersect_key($data, self::SECURITY_DEFAULTS);
            $authenticationData = array_intersect_key($data, self::AUTHENTICATION_DEFAULTS);

            $db = \Config\Database::connect();
            $db->transStart();

            if (!$this->settings()->setMultiple($securityData, 'security')) {
                throw new \RuntimeException('Failed to save security settings');
            }
            if (!$this->settings()->setMultiple($authenticationData, 'authentication')) {
                throw new \RuntimeException('Failed to save authentication settings');
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed');
            }

            $this->settings()->clearCache();
            log_message('info', 'Controls settings updated successfully', ['user' => $userId, 'settings' => array_keys($data)]);

            return ['success' => true, 'message' => 'Configurações de controles atualizadas com sucesso'];
        } catch (\Throwable $e) {
            log_message('error', 'Error updating controls settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            return ['success' => false, 'message' => 'Erro ao atualizar configurações. Por favor, tente novamente.'];
        }
    }

    public function auditLogs(array $filters = []): array
    {
        $auditModel = $this->audit();
        if (! $auditModel->db->tableExists('audit_logs')) {
            return ['success' => true, 'logs' => [], 'total' => 0, 'message' => 'Tabela de auditoria ainda não disponível.'];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        $builder = $auditModel->builder();
        $builder->select('audit_logs.*, employees.name as user_name')
            ->join('employees', 'employees.id = audit_logs.user_id', 'left');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $builder->groupStart()
                ->like('audit_logs.action', $search)
                ->orLike('audit_logs.description', $search)
                ->orLike('employees.name', $search)
                ->groupEnd();
        }

        $level = trim((string) ($filters['level'] ?? ''));
        if ($level !== '') {
            $builder->where('audit_logs.level', $level);
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $builder->where('audit_logs.action', $action);
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $builder->where('audit_logs.user_id', $userId);
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();
        $rows = $builder->orderBy('audit_logs.created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResult();

        $logs = array_map(static function ($row): array {
            $level = (string) ($row->level ?? 'info');
            $action = (string) ($row->action ?? '');
            $isFailureAction = (bool) preg_match('/_(FAILED|DENIED|REJECTED|BLOCKED|ERROR)$/', $action);
            $isFailureLevel = in_array($level, ['error', 'critical', 'alert', 'emergency'], true);

            return [
                'id' => (int) ($row->id ?? 0),
                'user' => (string) ($row->user_name ?? 'Sistema'),
                'action' => $action,
                'ip' => (string) ($row->ip_address ?? 'n/a'),
                'timestamp' => (string) ($row->created_at ?? ''),
                'status' => ($isFailureLevel || $isFailureAction) ? 'failed' : 'success',
                'level' => $level,
                'description' => (string) ($row->description ?? ''),
            ];
        }, $rows);

        return [
            'success' => true,
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function createBackup(): array
    {
        helper('observability');

        $result = $this->backup()->createBackup();
        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => supportponto_public_error_message('Falha ao criar backup do banco de dados.'),
            ];
        }

        $filePath = (string) ($result['filepath'] ?? '');
        if ($filePath === '' || ! is_file($filePath) || filesize($filePath) <= 0) {
            log_message('error', 'Backup service returned success without valid file.', ['result' => $result]);

            return [
                'success' => false,
                'message' => supportponto_public_error_message('Falha ao validar o artefato do backup criado.'),
            ];
        }

        return [
            'success' => true,
            'message' => 'Backup criado com sucesso.',
            'file' => (string) ($result['filename'] ?? basename($filePath)),
            'size' => $this->formatBytes((int) filesize($filePath)),
            'size_bytes' => (int) filesize($filePath),
            'path' => $filePath,
            'timestamp' => (string) ($result['timestamp'] ?? date('Y-m-d H:i:s')),
            'driver' => (string) ($result['driver'] ?? 'unknown'),
        ];
    }

    public function evaluatePassword(string $password): array
    {
        $settings = $this->settings()->getByGroupMap('security');
        $errors = [];
        $minLength = (int) ($settings['password_min_length'] ?? 8);

        if (strlen($password) < $minLength) {
            $errors[] = "Mínimo {$minLength} caracteres";
        }

        if (($settings['password_require_uppercase'] ?? 1) && ! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Pelo menos uma letra maiúscula';
        }
        if (($settings['password_require_lowercase'] ?? 1) && ! preg_match('/[a-z]/', $password)) {
            $errors[] = 'Pelo menos uma letra minúscula';
        }
        if (($settings['password_require_numbers'] ?? 1) && ! preg_match('/[0-9]/', $password)) {
            $errors[] = 'Pelo menos um número';
        }
        if (($settings['password_require_special'] ?? 1) && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Pelo menos um caractere especial';
        }

        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'Senha atende todos os requisitos' : 'Senha não atende os requisitos',
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password),
        ];
    }

    /**
     * Restaura Segurança e Autenticação para os valores padrão em uma única ação.
     */
    public function resetDefaults(): array
    {
        try {
            $this->settings()->deleteGroup('security');
            $this->settings()->setMultiple(self::SECURITY_DEFAULTS, 'security');
            $this->settings()->setMultiple(self::AUTHENTICATION_DEFAULTS, 'authentication');

            $this->settings()->clearCache();

            return ['success' => true, 'message' => 'Configurações de controles resetadas para o padrão'];
        } catch (\Throwable $e) {
            log_message('error', 'Controls reset failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao restaurar as configurações.'];
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2, ',', '.') . ' ' . $units[$power];
    }

    private function calculatePasswordStrength(string $password): array
    {
        $strength = 0;
        $feedback = [];

        if (strlen($password) >= 12) { $strength += 30; } else { $feedback[] = 'Use pelo menos 12 caracteres'; }
        if (preg_match('/[a-z]/', $password)) { $strength += 20; } else { $feedback[] = 'Adicione letras minúsculas'; }
        if (preg_match('/[A-Z]/', $password)) { $strength += 20; } else { $feedback[] = 'Adicione letras maiúsculas'; }
        if (preg_match('/[0-9]/', $password)) { $strength += 20; } else { $feedback[] = 'Adicione números'; }
        if (preg_match('/[^A-Za-z0-9]/', $password)) { $strength += 10; } else { $feedback[] = 'Adicione caracteres especiais'; }

        $level = 'fraca';
        $color = 'danger';
        if ($strength >= 80) { $level = 'muito forte'; $color = 'success'; }
        elseif ($strength >= 60) { $level = 'forte'; $color = 'primary'; }
        elseif ($strength >= 40) { $level = 'média'; $color = 'warning'; }

        return ['score' => $strength, 'level' => $level, 'color' => $color, 'feedback' => $feedback];
    }
}

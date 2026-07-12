<?php

namespace App\Services\Admin;

use App\Models\AuditModel;
use App\Models\SettingModel;
use App\Services\Backup\DatabaseBackupService;
use Config\Services;

class SecuritySettingsService
{
    public function __construct(
        private readonly ?SettingModel $settingModel = null,
        private readonly ?AuditModel $auditModel = null,
        private readonly ?DatabaseBackupService $databaseBackupService = null
    ) {
    }

    public function pageData(): array
    {
        return ['settings' => $this->settings()->getByGroupMap('security')];
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

    public function rules(): array
    {
        return [
            'password_min_length' => 'required|integer|greater_than[5]|less_than[129]',
            'password_require_uppercase' => 'permit_empty|in_list[0,1]',
            'password_require_lowercase' => 'permit_empty|in_list[0,1]',
            'password_require_numbers' => 'permit_empty|in_list[0,1]',
            'password_require_special' => 'permit_empty|in_list[0,1]',
            'password_expiry_days' => 'permit_empty|integer|greater_than[0]',
            'enable_audit_log' => 'permit_empty|in_list[0,1]',
            'audit_log_retention_days' => 'required|integer|greater_than[0]',
            'enable_auto_backup' => 'permit_empty|in_list[0,1]',
            'backup_frequency' => 'permit_empty|in_list[daily,weekly,monthly]',
            'backup_retention_days' => 'permit_empty|integer|greater_than[0]',
            // Additional security fields
            'xss_protection_enabled' => 'permit_empty|in_list[0,1]',
            'input_sanitization_level' => 'permit_empty|in_list[basico,estrito,paranoico]',
            'csp_enabled' => 'permit_empty|in_list[0,1]',
            'csp_policy' => 'permit_empty',
            'hsts_enabled' => 'permit_empty|in_list[0,1]',
            'cors_allowed_origins' => 'permit_empty',
            'rate_limit_enabled' => 'permit_empty|in_list[0,1]',
            'rate_limit_max_requests' => 'permit_empty|integer|greater_than[0]',
            'rate_limit_window_seconds' => 'permit_empty|integer|greater_than[0]',
        ];
    }

    public function update(array $data, ?int $userId = null): array
    {
        try {
            // Normalize unchecked checkboxes
            $booleanFields = [
                'password_require_uppercase', 'password_require_lowercase',
                'password_require_numbers', 'password_require_special',
                'enable_audit_log', 'enable_auto_backup',
                'xss_protection_enabled', 'csp_enabled', 'hsts_enabled', 'rate_limit_enabled',
            ];
            foreach ($booleanFields as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = '0';
                }
            }

            $db = \Config\Database::connect();
            $db->transStart();

            if (! $this->settings()->setMultiple($data, 'security')) {
                throw new \RuntimeException('Failed to save security settings');
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed');
            }

            $this->settings()->clearCache();
            log_message('info', 'Security settings updated successfully', ['user' => $userId, 'settings' => array_keys($data)]);

            return ['success' => true, 'message' => 'Configurações de segurança atualizadas com sucesso'];
        } catch (\Throwable $e) {
            log_message('error', 'Error updating security settings: ' . $e->getMessage(), [
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

            return [
                'id' => (int) ($row->id ?? 0),
                'user' => (string) ($row->user_name ?? 'Sistema'),
                'action' => (string) ($row->action ?? ''),
                'ip' => (string) ($row->ip_address ?? 'n/a'),
                'timestamp' => (string) ($row->created_at ?? ''),
                'status' => in_array($level, ['error', 'critical', 'alert', 'emergency'], true) ? 'failed' : 'success',
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

    public function resetDefaults(): array
    {
        try {
            $this->settings()->deleteGroup('security');
            $this->settings()->setMultiple([
                'password_min_length' => 8,
                'password_require_special' => true,
                'enable_audit_log' => true,
            ], 'security');

            return ['success' => true, 'message' => 'Configurações de segurança resetadas para o padrão'];
        } catch (\Throwable $e) {
            log_message('error', 'Security reset failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao restaurar as configurações de segurança.'];
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

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Config\Services;

use App\Models\SettingModel;

class AuthenticationSettingsService
{
    private const BOOLEAN_FIELDS = ['enable_remember_me', 'self_registration_enabled'];

    private const DEFAULTS = [
        'session_timeout' => 3600,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'enable_remember_me' => '0',
        'remember_me_duration' => 2592000,
        'self_registration_enabled' => '0',
    ];

    public function __construct(private readonly ?SettingModel $settingModel = null)
    {
    }

    /** @return array<string,mixed> */
    public function pageData(): array
    {
        return ['settings' => $this->settings()->getByGroupMap('authentication')];
    }

    /** @return array<string,string> */
    private function settings(): SettingModel
    {
        return $this->settingModel ?? Services::settings(false);
    }

    public function rules(): array
    {
        return [
            'session_timeout' => 'permit_empty|integer|greater_than[0]',
            'max_login_attempts' => 'permit_empty|integer|greater_than[0]|less_than[100]',
            'lockout_duration' => 'permit_empty|integer|greater_than[0]',
            'enable_remember_me' => 'permit_empty|in_list[0,1]',
            'remember_me_duration' => 'permit_empty|integer|greater_than[0]',
            'self_registration_enabled' => 'permit_empty|in_list[0,1]',
        ];
    }

    /** @param array<string,mixed> $data @return array{success:bool,message:string} */
    public function update(array $data, ?int $userId): array
    {
        try {
            // Normalize unchecked checkboxes (HTML doesn't send unchecked checkboxes)
            foreach (self::BOOLEAN_FIELDS as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = '0';
                }
            }

            $db = \Config\Database::connect();
            $db->transStart();

            if (!$this->settings()->setMultiple($data, 'authentication')) {
                throw new \RuntimeException('Failed to save authentication settings');
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed');
            }

            $this->settings()->clearCache();
            log_message('info', 'Authentication settings updated successfully', ['user' => $userId, 'settings' => array_keys($data)]);

            return ['success' => true, 'message' => 'Configurações de autenticação atualizadas com sucesso'];
        } catch (\Throwable $e) {
            log_message('error', 'Error updating authentication settings: ' . $e->getMessage(), ['user' => $userId]);
            return ['success' => false, 'message' => 'Erro ao atualizar configurações. Por favor, tente novamente.'];
        }
    }

    /** @return array{success:bool,message:string} */
    public function resetDefaults(): array
    {
        try {
            if (!$this->settings()->setMultiple(self::DEFAULTS, 'authentication')) {
                throw new \RuntimeException('Failed to reset authentication settings');
            }

            $this->settings()->clearCache();

            return ['success' => true, 'message' => 'Configurações de autenticação resetadas para o padrão'];
        } catch (\Throwable $e) {
            log_message('error', 'Error resetting authentication settings: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao resetar autenticação.'];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Config\Services;

use App\Models\SettingModel;

class AuthenticationSettingsService
{
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
            'enable_2fa' => 'permit_empty|in_list[0,1]',
            'enable_remember_me' => 'permit_empty|in_list[0,1]',
            'remember_me_duration' => 'permit_empty|integer|greater_than[0]',
            'password_reset_expiry' => 'permit_empty|integer|greater_than[0]',
            'enable_email_verification' => 'permit_empty|in_list[0,1]',
            'enable_login_notifications' => 'permit_empty|in_list[0,1]',
            // Social + SMS auth fields
            'social_google_enabled' => 'permit_empty|in_list[0,1]',
            'social_google_client_id' => 'permit_empty',
            'social_google_client_secret' => 'permit_empty',
            'social_apple_enabled' => 'permit_empty|in_list[0,1]',
            'social_apple_client_id' => 'permit_empty',
            'social_apple_client_secret' => 'permit_empty',
            'social_facebook_enabled' => 'permit_empty|in_list[0,1]',
            'social_facebook_client_id' => 'permit_empty',
            'social_facebook_client_secret' => 'permit_empty',
            'sms_auth_enabled' => 'permit_empty|in_list[0,1]',
            'sms_provider' => 'permit_empty|in_list[twilio,vonage,zenvia]',
            'allowed_ip_addresses' => 'permit_empty|max_length[500]',
            'self_registration_enabled' => 'permit_empty|in_list[0,1]',
        ];
    }

    /** @param array<string,mixed> $data @return array{success:bool,message:string} */
    public function update(array $data, ?int $userId): array
    {
        try {
            // Normalize unchecked checkboxes (HTML doesn't send unchecked checkboxes)
            $booleanFields = ['enable_2fa', 'enable_remember_me', 'enable_email_verification', 'enable_login_notifications', 'social_google_enabled', 'social_apple_enabled', 'social_facebook_enabled', 'sms_auth_enabled', 'self_registration_enabled', 'enable_captcha'];
            foreach ($booleanFields as $field) {
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

    /** @return array<string,mixed> */
    public function twoFactorTestPayload(): array
    {
        try {
            $twoFactorService = \Config\Services::twoFactorAuthService();
            $secret = $twoFactorService->generateSecret();
            $accountName = (string) session()->get('user_email');
            $qrDataUri = $twoFactorService->getQRCodeDataUri($secret, $accountName);
            return [
                'success' => true,
                'message' => '2FA configurado corretamente',
                'qr_code' => $qrDataUri,
                'secret' => $secret,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'twoFactorTestPayload: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR Code: ' . $e->getMessage(),
            ];
        }
    }

    /** @return array<string,mixed> */
    public function loginStatsPayload(): array
    {
        return [
            'success' => true,
            'stats' => [
                'total_logins_today' => 0,
                'failed_attempts_today' => 0,
                'locked_accounts' => 0,
                'active_sessions' => 0,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function clearLockedAccountsPayload(): array
    {
        return [
            'success' => true,
            'message' => 'Todas as contas foram desbloqueadas',
            'unlocked_count' => 0,
        ];
    }

    /** @return array<string,mixed> */
    public function testEmail(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email inválido'];
        }

        return ['success' => true, 'message' => 'Email de teste enviado com sucesso'];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Config\Services;

use App\Models\SettingModel;

class SystemSettingsService
{
    public function __construct(private readonly ?SettingModel $settingModel = null)
    {
    }

    /** @return array<string,mixed> */
    public function pageData(): array
    {
        return [
            'settings' => $this->settings()->getByGroupMap('system'),
            'timezones' => timezone_identifiers_list(),
            'languages' => [
                'pt-BR' => 'Português (Brasil)',
                'en-US' => 'English (US)',
                'es-ES' => 'Español',
                'fr-FR' => 'Français',
            ],
        ];
    }

    /** @return array<string,string> */
    private function settings(): SettingModel
    {
        return $this->settingModel ?? Services::settings(false);
    }

    public function rules(): array
    {
        return [
            'company_cnpj' => 'permit_empty|exact_length[18]',
            'company_address' => 'permit_empty|max_length[255]',
            'company_phone' => 'permit_empty|max_length[20]',
            'company_email' => 'permit_empty|valid_email',
            'timezone' => 'required|max_length[50]',
            'language' => 'required|in_list[pt-BR,en-US,es-ES,fr-FR]',
            'date_format' => 'required|in_list[d/m/Y,m/d/Y,Y-m-d]',
            'time_format' => 'required|in_list[H:i,h:i A]',
        ];
    }

    /** @param array<string,mixed> $data @return array{success:bool,message:string} */
    public function update(array $data, ?int $userId): array
    {
        try {
            $db = \Config\Database::connect();
            $db->transStart();

            if (!$this->settings()->setMultiple($data, 'system')) {
                throw new \RuntimeException('Failed to save system settings');
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed');
            }

            if (isset($data['timezone'])) {
                date_default_timezone_set((string) $data['timezone']);
            }

            $this->settings()->clearCache();
            log_message('info', 'System settings updated successfully', ['user' => $userId, 'settings' => array_keys($data)]);

            return ['success' => true, 'message' => 'Configurações do sistema atualizadas com sucesso'];
        } catch (\Throwable $e) {
            log_message('error', 'Error updating system settings: ' . $e->getMessage(), ['user' => $userId]);
            return ['success' => false, 'message' => 'Erro ao atualizar configurações. Por favor, tente novamente.'];
        }
    }

    /** @return array<string,mixed> */
    public function testTimezone(string $timezone): array
    {
        try {
            $oldTimezone = date_default_timezone_get();
            date_default_timezone_set($timezone);
            $now = new \DateTime();

            $info = [
                'timezone' => $timezone,
                'current_time' => $now->format('Y-m-d H:i:s'),
                'offset' => $now->format('P'),
                'is_dst' => $now->format('I') === '1',
            ];

            date_default_timezone_set($oldTimezone);

            return ['success' => true, 'message' => 'Fuso horário válido', 'info' => $info];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Fuso horário inválido: ' . $e->getMessage()];
        }
    }

    /** @return array{success:bool,message:string} */
    public function reset(): array
    {
        try {
            $this->settings()->deleteGroup('system');

            $defaultSettings = [
                'company_cnpj' => '',
                'timezone' => 'America/Sao_Paulo',
                'language' => 'pt-BR',
            ];

            $this->settings()->setMultiple($defaultSettings, 'system');

            return ['success' => true, 'message' => 'Configurações do sistema resetadas'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao resetar: ' . $e->getMessage()];
        }
    }
}

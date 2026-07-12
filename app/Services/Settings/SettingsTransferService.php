<?php

namespace App\Services\Settings;

use App\Models\SettingModel;
use RuntimeException;

class SettingsTransferService
{
    private array $allowedGroups = [
        'general',
        'system',
        'security',
        'authentication',
        'notifications',
        'appearance',
        'time_tracking',
        'biometric',
        'lgpd',
        'email',
    ];

    private array $blockedGroups = [
        'apis',
        'api',
        'certificates',
        'certificate',
        'oauth',
    ];

    private array $blockedKeyPatterns = [
        '/password/i',
        '/secret/i',
        '/token/i',
        '/authorization/i',
        '/certificate_password/i',
        '/private_key/i',
    ];

    public function __construct(private readonly ?SettingModel $settingModel = null)
    {
    }

    /** @param list<string>|null $groupsFilter */
    public function exportPayload(?array $groupsFilter = null): array
    {
        $payload = [];
        $allowedGroups = $this->normalizeGroupsFilter($groupsFilter);

        foreach ($this->settingModel()->getForExport() as $row) {
            $group = (string) ($row['group'] ?? $row['setting_group'] ?? 'general');
            $key = (string) ($row['key'] ?? $row['setting_key'] ?? '');
            $isEncrypted = (bool) ($row['is_encrypted'] ?? false);

            if ($key === '' || $isEncrypted || ! $this->isAllowedGroup($group) || ! $this->isAllowedKey($group, $key)) {
                continue;
            }

            if ($allowedGroups !== [] && ! in_array($group, $allowedGroups, true)) {
                continue;
            }

            $payload[$group][$key] = $row['value'] ?? $row['setting_value'] ?? null;
        }

        ksort($payload);
        foreach ($payload as &$groupSettings) {
            ksort($groupSettings);
        }

        return [
            'meta' => [
                'format' => 'supportponto.settings.export',
                'version' => 1,
                'generated_at' => date('c'),
            ],
            'settings' => $payload,
        ];
    }

    public function importPayload(array $payload): array
    {
        $settings = $this->extractSettingsPayload($payload);
        if ($settings === []) {
            throw new RuntimeException('Arquivo sem configurações importáveis.');
        }

        foreach ($settings as $group => $groupSettings) {
            if (! is_string($group) || ! is_array($groupSettings) || ! $this->isAllowedGroup($group)) {
                throw new RuntimeException('O arquivo contém grupos de configuração não permitidos.');
            }

            $allowedKeys = $this->resolveAllowedKeysForGroup($group);
            if ($allowedKeys === []) {
                throw new RuntimeException('O grupo ' . $group . ' não possui chaves importáveis neste ambiente.');
            }

            foreach ($groupSettings as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowedKeys, true) || ! $this->isAllowedKey($group, $key)) {
                    throw new RuntimeException('O arquivo contém chaves não permitidas para importação.');
                }

                $groupSettings[$key] = $this->normalizeValue($value);
            }

            if (! $this->settingModel()->setMultiple($groupSettings, $group)) {
                throw new RuntimeException('Falha ao persistir configurações do grupo ' . $group . '.');
            }
        }

        $this->settingModel()->clearCache();

        return [
            'success' => true,
            'message' => 'Configurações importadas com sucesso.',
            'groups' => array_keys($settings),
        ];
    }

    private function extractSettingsPayload(array $payload): array
    {
        $settings = $payload['settings'] ?? $payload;

        return is_array($settings) ? $settings : [];
    }

    private function settingModel(): SettingModel
    {
        return $this->settingModel ?? new SettingModel();
    }

    private function isAllowedGroup(string $group): bool
    {
        return in_array($group, $this->allowedGroups, true) && ! in_array($group, $this->blockedGroups, true);
    }

    private function isAllowedKey(string $group, string $key): bool
    {
        if (! $this->isAllowedGroup($group)) {
            return false;
        }

        foreach ($this->blockedKeyPatterns as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return false;
            }
        }

        return true;
    }

    private function resolveAllowedKeysForGroup(string $group): array
    {
        $allowed = [];

        foreach ($this->settingModel()->getByGroup($group) as $row) {
            $key = is_array($row)
                ? (string) ($row['key'] ?? $row['setting_key'] ?? '')
                : (string) ($row->key ?? $row->setting_key ?? '');

            if ($key !== '' && $this->isAllowedKey($group, $key)) {
                $allowed[] = $key;
            }
        }

        return array_values(array_unique($allowed));
    }


    /** @return list<string> */
    public function allowedGroups(): array
    {
        return array_values($this->allowedGroups);
    }

    /** @param list<string>|null $groupsFilter @return list<string> */
    private function normalizeGroupsFilter(?array $groupsFilter): array
    {
        if ($groupsFilter === null) {
            return [];
        }

        $normalized = [];
        foreach ($groupsFilter as $group) {
            if (! is_string($group)) {
                continue;
            }

            $group = trim($group);
            if ($group === '' || ! $this->isAllowedGroup($group)) {
                continue;
            }

            $normalized[] = $group;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        throw new RuntimeException('Valor de configuração em formato inválido.');
    }
}

<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\SettingModel;

abstract class BaseSettingsController extends BaseController
{
    protected SettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = model(SettingModel::class);
    }

    protected function requireAdminAccess(): void
    {
        $this->requireRole('admin');
    }

    protected function jsonSettingsResponse(bool $success, string $message, int $status = 200, array $extra = [])
    {
        return $this->response->setStatusCode($status)->setJSON(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra));
    }

    protected function normalizeBoolean($value): string
    {
        if ($value === null || $value === '' || $value === '0' || $value === 'false' || $value === false) {
            return '0';
        }

        return '1';
    }

    protected function saveSettingsGroup(string $group, array $schema): void
    {
        $postData = security_sanitize($this->request->getPost() ?? []);

        supportponto_log_event('info', 'settings', 'save_group_started', [
            'group' => $group,
            'fields_received' => count($postData),
            'schema_fields' => array_keys($schema),
            'user_id' => $this->session?->get('user_id'),
        ]);

        foreach ($schema as $field => $type) {
            if (!array_key_exists($field, $postData)) {
                continue;
            }

            if ($type === 'boolean') {
                $value = $this->normalizeBoolean($postData[$field]);
                $this->settingModel->setSetting($field, $value, 'boolean', $group);
                continue;
            }

            if ($type === 'integer') {
                $value = (string) ((int) ($postData[$field] ?? 0));
                $this->settingModel->setSetting($field, $value, 'integer', $group);
                continue;
            }

            if ($type === 'json') {
                $value = $postData[$field] ?? [];
                $this->settingModel->setSetting($field, is_array($value) ? $value : (string) $value, 'json', $group);
                continue;
            }

            if ($type === 'encrypted') {
                if ($postData[$field] === '' || $postData[$field] === null) {
                    continue;
                }

                $this->settingModel->setSetting($field, (string) $postData[$field], 'string', $group, true);
                continue;
            }

            $this->settingModel->setSetting($field, (string) ($postData[$field] ?? ''), 'string', $group);
        }

        $this->settingModel->clearCache();

        supportponto_log_event('notice', 'settings', 'save_group_completed', [
            'group' => $group,
            'fields_processed' => count(array_intersect(array_keys($postData), array_keys($schema))),
            'user_id' => $this->session?->get('user_id'),
        ]);
    }
}

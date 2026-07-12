<?php

namespace App\Database\Seeds;

use App\Models\SettingModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Biometric Settings Seeder
 *
 * Seeds default configuration for biometric fingerprint system
 * using the canonical settings model/table structure.
 */
class BiometricSettingsSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('settings')) {
            throw new RuntimeException("Tabela obrigatória 'settings' não existe. Execute as migrations antes do BiometricSettingsSeeder.");
        }

        /** @var SettingModel $settingsModel */
        $settingsModel = model(SettingModel::class);

        $settings = [
            ['key' => 'biometric_fingerprint_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_face_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_fingerprint_threshold', 'value' => '30', 'type' => 'integer', 'group' => 'biometric'],
            ['key' => 'biometric_min_quality_score', 'value' => '30', 'type' => 'integer', 'group' => 'biometric'],
            ['key' => 'biometric_allow_duplicate_check', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_max_templates_per_user', 'value' => '5', 'type' => 'integer', 'group' => 'biometric'],
            ['key' => 'biometric_encryption_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_audit_all_attempts', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_rate_limit_attempts', 'value' => '10', 'type' => 'integer', 'group' => 'biometric'],
            ['key' => 'biometric_rate_limit_window', 'value' => '60', 'type' => 'integer', 'group' => 'biometric'],
            ['key' => 'biometric_require_consent', 'value' => '1', 'type' => 'boolean', 'group' => 'biometric'],
            ['key' => 'biometric_consent_version', 'value' => '1.0', 'type' => 'string', 'group' => 'biometric'],
            ['key' => 'sourceafis_mode', 'value' => 'native', 'type' => 'string', 'group' => 'biometric'],
            ['key' => 'sourceafis_api_url', 'value' => 'http://localhost:5001', 'type' => 'string', 'group' => 'biometric'],
            ['key' => 'sourceafis_timeout', 'value' => '30', 'type' => 'integer', 'group' => 'biometric'],
        ];

        foreach ($settings as $setting) {
            $settingsModel->setSetting(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['group'],
                false
            );

            echo "Upserted setting: {$setting['key']}\n";
        }
    }
}

<?php

namespace App\Controllers\Settings;

use App\Services\Settings\System\SystemBrandingAssetService;

class SystemSettingsController extends BaseSettingsController
{
    public function __construct(private readonly SystemBrandingAssetService $brandingAssetService = new SystemBrandingAssetService())
    {
        parent::__construct();
    }

    public function saveGeneral()
    {
        $this->requireAdminAccess();

        try {
            $this->saveSettingsGroup('general', [
                'company_name' => 'string',
                'company_trade_name' => 'string',
                'company_cnpj' => 'string',
                'company_ie' => 'string',
                'company_cei' => 'string',
                'company_address' => 'string',
                'company_cep' => 'string',
                'company_city' => 'string',
                'company_state' => 'string',
                'company_phone' => 'string',
                'primary_color' => 'string',
                'secondary_color' => 'string',
                'timezone' => 'string',
            ]);

            $assetResult = $this->brandingAssetService->processGeneralAssets(
                $this->request->getFile('company_logo'),
                $this->request->getFile('company_favicon'),
                $this->request->getFile('login_background')
            );

            if (!($assetResult['success'] ?? false)) {
                return $this->jsonSettingsResponse(false, $assetResult['message'] ?? 'Erro ao processar arquivos.', (int) ($assetResult['status'] ?? 422));
            }

            supportponto_log_event('notice', 'settings', 'general_saved', [
                'user_id' => $this->session?->get('user_id'),
                'assets_processed' => true,
            ]);

            return $this->jsonSettingsResponse(true, $assetResult['message'] ?? 'Configurações gerais salvas');
        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'general_save_failed', $e, [
                'user_id' => $this->session?->get('user_id'),
            ]);
            return $this->jsonSettingsResponse(false, 'Erro ao salvar', 500);
        }
    }

    public function saveWorkday()
    {
        return $this->saveSettingsSection('workday', [
            'workday_start' => 'string',
            'workday_end' => 'string',
            'mandatory_break_hours' => 'string',
            'late_tolerance_minutes' => 'integer',
            'early_tolerance_minutes' => 'integer',
            'overtime_tolerance_minutes' => 'integer',
            'business_days' => 'json',
        ], 'Configurações de jornada salvas', 'workday');
    }

    public function saveGeolocation()
    {
        return $this->saveSettingsSection('geolocation', [
            'geolocation_enabled' => 'boolean',
            'geolocation_radius' => 'integer',
            'geolocation_strict' => 'boolean',
        ], 'Configurações de geolocalização salvas', 'geolocation');
    }

    public function saveNotifications()
    {
        return $this->saveSettingsSection('notifications', [
            'smtp_host' => 'string',
            'smtp_port' => 'integer',
            'smtp_secure' => 'string',
            'smtp_user' => 'string',
            'smtp_password' => 'encrypted',
            'smtp_from_email' => 'string',
            'smtp_from_name' => 'string',
            'email_template_welcome' => 'string',
            'email_template_punch_reminder' => 'string',
        ], 'Configurações de notificações salvas', 'notifications');
    }

    public function saveBiometry()
    {
        return $this->saveSettingsSection('biometry', [
            'biometry_enabled' => 'boolean',
            'facial_recognition_enabled' => 'boolean',
            'facial_recognition_threshold' => 'string',
            'fingerprint_enabled' => 'boolean',
        ], 'Configurações de biometria salvas', 'biometry');
    }

    public function saveApis()
    {
        return $this->saveSettingsSection('apis', [
            'api_facial_key' => 'encrypted',
            'api_biometry_key' => 'encrypted',
            'api_geolocation_key' => 'encrypted',
            'api_fcm_key' => 'encrypted',
        ], 'Configurações de APIs salvas', 'apis');
    }


    public function saveIcp()
    {
        return $this->saveSettingsSection('icp', [
            'icp_enabled' => 'boolean',
            'icp_certificate_type' => 'string',
            'icp_sign_punches' => 'boolean',
        ], 'Configurações ICP-Brasil salvas', 'icp');
    }

    public function saveICPBrasil()
    {
        return $this->saveIcp();
    }

    public function saveLgpd()
    {
        return $this->saveSettingsSection('lgpd', [
            'lgpd_consent_required' => 'boolean',
            'lgpd_data_retention_days' => 'integer',
            'lgpd_anonymize_on_delete' => 'boolean',
            'lgpd_dpo_email' => 'string',
        ], 'Configurações LGPD salvas', 'lgpd');
    }

    public function saveBackup()
    {
        return $this->saveSettingsSection('backup', [
            'backup_enabled' => 'boolean',
            'backup_frequency' => 'string',
            'backup_retention_days' => 'integer',
            'backup_storage' => 'string',
        ], 'Configurações de backup salvas', 'backup');
    }

    public function saveLogoAssets()
    {
        $this->requireAdminAccess();

        try {
            $result = $this->brandingAssetService->saveLogoAssets(
                $this->request->getFile('logo_file'),
                $this->request->getPost('crop_data')
            );

            if (!($result['success'] ?? false)) {
                return $this->jsonSettingsResponse(false, $result['message'] ?? 'Erro ao processar logo.', (int) ($result['status'] ?? 422));
            }

            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            log_message('error', 'saveLogoAssets error: ' . $e->getMessage());
            return $this->jsonSettingsResponse(false, 'Erro ao processar logo.', 500);
        }
    }

    private function saveSettingsSection(string $group, array $schema, string $successMessage, string $errorContext)
    {
        $this->requireAdminAccess();

        try {
            $this->saveSettingsGroup($group, $schema);
            return $this->jsonSettingsResponse(true, $successMessage);
        } catch (\Throwable $e) {
            log_message('error', "Error saving {$errorContext} settings: " . $e->getMessage());
            return $this->jsonSettingsResponse(false, 'Erro ao salvar', 500);
        }
    }
}

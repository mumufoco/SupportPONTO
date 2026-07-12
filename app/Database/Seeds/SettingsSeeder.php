<?php

namespace App\Database\Seeds;

use App\Models\SettingModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('settings')) {
            throw new RuntimeException("Tabela obrigatória 'settings' não existe. Execute as migrations antes do SettingsSeeder.");
        }

        /** @var SettingModel $settingsModel */
        $settingsModel = model(SettingModel::class);

        $companyName = trim((string) env('company.name', env('COMPANY_NAME', 'Empresa Exemplo LTDA')));
        $companyCnpj = trim((string) env('company.cnpj', env('COMPANY_CNPJ', '00.000.000/0001-00')));
        $deepfaceApiUrl = trim((string) env('DEEPFACE_API_URL', 'http://localhost:5000'));

        $settings = [
            ['key' => 'company_name', 'value' => $companyName, 'type' => 'string', 'group' => 'general', 'description' => 'Nome da empresa'],
            ['key' => 'company_cnpj', 'value' => $companyCnpj !== '' ? $companyCnpj : '00.000.000/0001-00', 'type' => 'string', 'group' => 'general', 'description' => 'CNPJ da empresa'],
            ['key' => 'company_address', 'value' => 'Rua Exemplo, 123 - Centro - São Paulo/SP - CEP 01000-000', 'type' => 'string', 'group' => 'general', 'description' => 'Endereço da empresa'],
            ['key' => 'company_phone', 'value' => '(11) 1234-5678', 'type' => 'string', 'group' => 'general', 'description' => 'Telefone principal'],
            ['key' => 'company_email', 'value' => 'contato@empresa.com.br', 'type' => 'string', 'group' => 'general', 'description' => 'E-mail principal'],
            ['key' => 'timezone', 'value' => 'America/Sao_Paulo', 'type' => 'string', 'group' => 'general', 'description' => 'Fuso horário padrão'],
            ['key' => 'work_schedule_start', 'value' => '08:00', 'type' => 'string', 'group' => 'jornada', 'description' => 'Início da jornada'],
            ['key' => 'work_schedule_end', 'value' => '18:00', 'type' => 'string', 'group' => 'jornada', 'description' => 'Fim da jornada'],
            ['key' => 'expected_hours_daily', 'value' => '8.00', 'type' => 'string', 'group' => 'jornada', 'description' => 'Carga horária diária esperada'],
            ['key' => 'interval_required_minutes', 'value' => '60', 'type' => 'integer', 'group' => 'jornada', 'description' => 'Intervalo obrigatório em minutos'],
            ['key' => 'tolerance_minutes', 'value' => '15', 'type' => 'integer', 'group' => 'jornada', 'description' => 'Tolerância em minutos'],
            ['key' => 'work_days', 'value' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'type' => 'json', 'group' => 'jornada', 'description' => 'Dias úteis da semana'],
            ['key' => 'geolocation_required', 'value' => true, 'type' => 'boolean', 'group' => 'geolocation', 'description' => 'Exigir geolocalização'],
            ['key' => 'geofence_default_radius', 'value' => 100, 'type' => 'integer', 'group' => 'geolocation', 'description' => 'Raio padrão da geofence'],
            ['key' => 'company_latitude', 'value' => '-23.5505', 'type' => 'string', 'group' => 'geolocation', 'description' => 'Latitude da empresa'],
            ['key' => 'company_longitude', 'value' => '-46.6333', 'type' => 'string', 'group' => 'geolocation', 'description' => 'Longitude da empresa'],
            ['key' => 'notifications_email_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'notifications', 'description' => 'Habilitar notificações por e-mail'],
            ['key' => 'notifications_push_enabled', 'value' => false, 'type' => 'boolean', 'group' => 'notifications', 'description' => 'Habilitar notificações push'],
            ['key' => 'notifications_sms_enabled', 'value' => false, 'type' => 'boolean', 'group' => 'notifications', 'description' => 'Habilitar notificações por SMS'],
            ['key' => 'punch_reminder_minutes', 'value' => 30, 'type' => 'integer', 'group' => 'notifications', 'description' => 'Minutos antes do lembrete de ponto'],
            ['key' => 'deepface_api_url', 'value' => $deepfaceApiUrl !== '' ? $deepfaceApiUrl : 'http://localhost:5000', 'type' => 'string', 'group' => 'biometria', 'description' => 'URL da API do DeepFace'],
            ['key' => 'deepface_internal_token', 'value' => '', 'type' => 'string', 'group' => 'biometria', 'description' => 'Token interno para comunicação segura entre o SupportPONTO e o microserviço DeepFace'],
            ['key' => 'health_details_token', 'value' => '', 'type' => 'string', 'group' => 'monitoring', 'description' => 'Token interno para acesso detalhado aos health checks de produção'],
            ['key' => 'deepface_threshold', 'value' => '0.40', 'type' => 'string', 'group' => 'biometria', 'description' => 'Threshold de reconhecimento facial'],
            ['key' => 'deepface_model', 'value' => 'VGG-Face', 'type' => 'string', 'group' => 'biometria', 'description' => 'Modelo do DeepFace'],
            ['key' => 'deepface_detector', 'value' => 'opencv', 'type' => 'string', 'group' => 'biometria', 'description' => 'Detector utilizado'],
            ['key' => 'anti_spoofing_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'biometria', 'description' => 'Ativar anti-spoofing'],
            ['key' => 'upload_max_size', 'value' => 5242880, 'type' => 'integer', 'group' => 'upload', 'description' => 'Tamanho máximo de upload'],
            ['key' => 'upload_allowed_types', 'value' => 'jpg,jpeg,png,pdf', 'type' => 'string', 'group' => 'upload', 'description' => 'Tipos permitidos'],
            ['key' => 'lgpd_dpo_name', 'value' => 'Nome do DPO', 'type' => 'string', 'group' => 'lgpd', 'description' => 'Nome do encarregado LGPD'],
            ['key' => 'lgpd_dpo_email', 'value' => 'dpo@empresa.com.br', 'type' => 'string', 'group' => 'lgpd', 'description' => 'E-mail do encarregado LGPD'],
            ['key' => 'data_retention_days', 'value' => 3650, 'type' => 'integer', 'group' => 'lgpd', 'description' => 'Retenção de dados em dias'],
            ['key' => 'audit_retention_days', 'value' => 3650, 'type' => 'integer', 'group' => 'lgpd', 'description' => 'Retenção de auditoria em dias'],
            ['key' => 'cache_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'cache', 'description' => 'Cache habilitado'],
            ['key' => 'cache_ttl', 'value' => 3600, 'type' => 'integer', 'group' => 'cache', 'description' => 'TTL do cache'],
            ['key' => 'session_timeout', 'value' => 7200, 'type' => 'integer', 'group' => 'security', 'description' => 'Timeout de sessão'],
            ['key' => 'brute_force_attempts', 'value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Tentativas antes do bloqueio'],
            ['key' => 'brute_force_lockout_minutes', 'value' => 15, 'type' => 'integer', 'group' => 'security', 'description' => 'Tempo de bloqueio em minutos'],
            ['key' => 'rate_limit_facial_per_minute', 'value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Limite facial por minuto'],
        ];

        foreach ($settings as $setting) {
            $settingsModel->setSetting(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['group'],
                false,
            );

            if (! empty($setting['description'])) {
                $row = $settingsModel->where('key', $setting['key'])->orWhere('setting_key', $setting['key'])->first();
                $id = is_array($row) ? ($row['id'] ?? null) : ($row->id ?? null);
                if ($id !== null) {
                    $settingsModel->update($id, ['description' => $setting['description']]);
                }
            }
        }

        $settingsModel->clearCache();
    }
}

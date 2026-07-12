<?php

namespace App\Commands;

use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class PrepareE2E extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'e2e:prepare';
    protected $description = 'Reseta dados previsíveis para os testes end-to-end do SupportPONTO.';
    protected $usage = 'e2e:prepare [--require-geolocation 0|1] [--require-geofence 0|1]';
    protected $options = [
        '--require-geolocation' => 'Exige geolocalização no fluxo de ponto (0 ou 1).',
        '--require-geofence' => 'Exige cerca virtual válida no fluxo de ponto (0 ou 1).',
    ];

    public function run(array $params)
    {
        $db = Database::connect();

        $this->truncateTableIfExists($db, 'settings');
        $this->truncateTableIfExists($db, 'oauth_refresh_tokens');
        $this->truncateTableIfExists($db, 'oauth_access_tokens');
        $this->truncateTableIfExists($db, 'report_queue');
        $this->truncateTableIfExists($db, 'audit_logs');

        CLI::write('Seeding base E2E data (testing namespace)...', 'yellow');
        $this->call('db:seed', ['App\\Database\\Seeds\\Testing\\TestDataSeeder']);

        $requireGeolocation = $this->normalizeBooleanOption(CLI::getOption('require-geolocation'));
        $requireGeofence = $this->normalizeBooleanOption(CLI::getOption('require-geofence'));

        $settingModel = new SettingModel();
        $settings = [
            'punch_method_code_enabled' => '1',
            'punch_method_cpf_enabled' => '1',
            'punch_method_qr_enabled' => '1',
            'punch_method_face_enabled' => '1',
            'punch_method_fingerprint_enabled' => '0',
            'require_geolocation' => $requireGeolocation ? '1' : '0',
            'require_geofence' => $requireGeofence ? '1' : '0',
            'authorized_kiosk_ips' => '',
            'company_name' => 'Empresa Teste LTDA',
        ];

        foreach ($settings as $key => $value) {
            $group = match ($key) {
                'require_geolocation', 'require_geofence', 'authorized_kiosk_ips' => 'geolocation',
                default => 'general',
            };

            $settingModel->setSetting($key, $value, 'string', $group);
        }

        CLI::write('E2E dataset ready (test-only fixtures).', 'green');
        CLI::write('Admin: admin@empresateste.com.br / TestOnly-Admin!2026', 'green');
        CLI::write('Gestora: maria.gestora@empresateste.com.br / TestOnly-Manager!2026', 'green');
        CLI::write('Colaborador: carlos.dev@empresateste.com.br / TestOnly-Developer!2026', 'green');
        CLI::write('Flags: require_geolocation=' . ($requireGeolocation ? '1' : '0') . ', require_geofence=' . ($requireGeofence ? '1' : '0'), 'cyan');
    }

    private function truncateTableIfExists($db, string $table): void
    {
        if (! $db->tableExists($table)) {
            return;
        }

        try {
            $db->disableForeignKeyChecks();
            $db->table($table)->truncate();
        } catch (\Throwable $e) {
            CLI::write("Skipping truncate for {$table}: {$e->getMessage()}", 'yellow');
        } finally {
            $db->enableForeignKeyChecks();
        }
    }

    private function normalizeBooleanOption(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}

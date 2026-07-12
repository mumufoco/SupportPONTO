<?php

namespace App\Database\Seeds\Testing;

use CodeIgniter\Database\Seeder;

/**
 * Test Seeder
 *
 * Seeds database with test data for PHPUnit tests
 */
class TestSeeder extends Seeder
{
    private const TEST_ADMIN_PASSWORD = 'TestOnly-Admin!2026';
    private const TEST_MANAGER_PASSWORD = 'TestOnly-Manager!2026';
    private const TEST_EMPLOYEE_PASSWORD = 'TestOnly-Employee!2026';

    public function run()
    {
        $this->assertTestingEnvironment();

        // Seed admin user
        $this->db->table('employees')->insert([
            'name' => 'Administrador do Sistema',
            'cpf' => '000.000.000-00',
            'email' => 'admin@pontoeletronico.com.br',
            'password' => password_hash(self::TEST_ADMIN_PASSWORD, PASSWORD_ARGON2ID),
            'role' => 'admin',
            'department' => 'TI',
            'phone' => '(11) 99999-9999',
            'admission_date' => '2024-01-01',
            'daily_hours' => 8.0,
            'weekly_hours' => 44.0,
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'lunch_start' => '12:00:00',
            'lunch_end' => '13:00:00',
            'active' => true,
            'lgpd_consent' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Seed manager user
        $this->db->table('employees')->insert([
            'name' => 'Gestor de RH',
            'cpf' => '111.111.111-11',
            'email' => 'gestor@pontoeletronico.com.br',
            'password' => password_hash(self::TEST_MANAGER_PASSWORD, PASSWORD_ARGON2ID),
            'role' => 'gestor',
            'department' => 'RH',
            'phone' => '(11) 98888-8888',
            'admission_date' => '2024-01-15',
            'daily_hours' => 8.0,
            'weekly_hours' => 44.0,
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'lunch_start' => '12:00:00',
            'lunch_end' => '13:00:00',
            'active' => true,
            'lgpd_consent' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Seed regular employee
        $this->db->table('employees')->insert([
            'name' => 'Funcionário Teste',
            'cpf' => '222.222.222-22',
            'email' => 'funcionario@pontoeletronico.com.br',
            'password' => password_hash(self::TEST_EMPLOYEE_PASSWORD, PASSWORD_ARGON2ID),
            'role' => 'funcionario',
            'department' => 'TI',
            'phone' => '(11) 97777-7777',
            'admission_date' => '2024-02-01',
            'daily_hours' => 8.0,
            'weekly_hours' => 44.0,
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'lunch_start' => '12:00:00',
            'lunch_end' => '13:00:00',
            'active' => true,
            'lgpd_consent' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Seed basic settings
        $settings = [
            ['category' => 'company', 'key' => 'company_name', 'value' => 'Empresa Teste LTDA', 'type' => 'string'],
            ['category' => 'company', 'key' => 'company_cnpj', 'value' => '00.000.000/0001-00', 'type' => 'string'],
            ['category' => 'work', 'key' => 'default_daily_hours', 'value' => '8', 'type' => 'number'],
            ['category' => 'work', 'key' => 'default_weekly_hours', 'value' => '44', 'type' => 'number'],
            ['category' => 'work', 'key' => 'tolerance_minutes', 'value' => '10', 'type' => 'number'],
            ['category' => 'security', 'key' => 'require_strong_password', 'value' => 'true', 'type' => 'boolean'],
            ['category' => 'security', 'key' => 'max_login_attempts', 'value' => '5', 'type' => 'number'],
            ['category' => 'lgpd', 'key' => 'consent_required', 'value' => 'true', 'type' => 'boolean'],
            ['category' => 'lgpd', 'key' => 'data_retention_days', 'value' => '365', 'type' => 'number'],
        ];

        foreach ($settings as $setting) {
            $this->db->table('settings')->insert($setting);
        }

        // Seed geofence
        $this->db->table('geofences')->insert([
            'name' => 'Sede Principal',
            'description' => 'Escritório central da empresa',
            'latitude' => -23.550520,
            'longitude' => -46.633308,
            'radius_meters' => 100,
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function assertTestingEnvironment(): void
    {
        if (ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('App\\Database\\Seeds\\Testing\\TestSeeder is restricted to CI_ENVIRONMENT=testing.');
        }
    }
}

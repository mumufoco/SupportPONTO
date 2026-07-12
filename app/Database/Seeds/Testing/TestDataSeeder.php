<?php

namespace App\Database\Seeds\Testing;

use CodeIgniter\Database\Seeder;

/**
 * Test Data Seeder
 *
 * Populates database with realistic test data for development and testing
 */
class TestDataSeeder extends Seeder
{
    private const TEST_ADMIN_PASSWORD = 'TestOnly-Admin!2026';
    private const TEST_MANAGER_PASSWORD = 'TestOnly-Manager!2026';
    private const TEST_DEV_PASSWORD = 'TestOnly-Developer!2026';
    private const TEST_ANA_PASSWORD = 'TestOnly-Ana!2026';
    private const TEST_PEDRO_PASSWORD = 'TestOnly-Pedro!2026';



    private function hashTestPassword(string $password): string
    {
        return password_hash(
            $password,
            PASSWORD_ARGON2ID,
            [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3,
            ]
        );
    }

    public function run()
    {
        $this->assertTestingEnvironment();

        // ============================================================================
        // 0. CLEAN EXISTING TEST DATA (If exists)
        // ============================================================================

        echo "[LOG] Limpando dados de teste existentes...\\n";

        // Delete in reverse order of foreign keys
        $this->db->table('notifications')->truncate();
        $this->db->table('justifications')->truncate();
        $this->db->table('time_punches')->truncate();
        $this->db->table('geofences')->truncate();
        $this->db->table('employees')->truncate();
        $this->db->table('companies')->truncate();

        echo "[LOG] Dados limpos. Iniciando população...\\n\\n";

        // ============================================================================
        // 1. COMPANY
        // ============================================================================

        $companyData = [
            'name'                   => 'Empresa Teste LTDA',
            'trade_name'             => 'Teste Corp',
            'cnpj'                   => '12.345.678/0001-90',
            'state_registration'     => '123.456.789.012',
            'municipal_registration' => '9876543',
            'address'                => 'Rua das Flores, 123',
            'city'                   => 'São Paulo',
            'state'                  => 'SP',
            'zip_code'               => '01234-567',
            'phone'                  => '(11) 3456-7890',
            'email'                  => 'contato@empresateste.com.br',
            'website'                => 'https://empresateste.com.br',
            'active'                 => 1,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ];

        $this->db->table('companies')->insert($companyData);
        $companyId = $this->db->insertID();

        // ============================================================================
        // 2. EMPLOYEES (Created first to use admin as creator for geofence)
        // ============================================================================

        $employees = [
            [
                'name'                  => 'Admin Sistema',
                'email'                 => 'admin@empresateste.com.br',
                'password'              => $this->hashTestPassword(self::TEST_ADMIN_PASSWORD),
                'cpf'                   => '111.111.111-11',
                'unique_code'           => '1111',
                'role'                  => 'admin',
                'department'            => 'TI',
                'position'              => 'Administrador de Sistemas',
                'expected_hours_daily'  => 8.0,
                'work_schedule_start'   => '08:00:00',
                'work_schedule_end'     => '17:00:00',
                'extra_hours_balance'   => 0,
                'owed_hours_balance'    => 0,
                'active'                => 1,
                'has_face_biometric'    => 1,
                'has_fingerprint_biometric' => 0,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ],
            [
                'name'                  => 'Maria Gestora',
                'email'                 => 'maria.gestora@empresateste.com.br',
                'password'              => $this->hashTestPassword(self::TEST_MANAGER_PASSWORD),
                'cpf'                   => '222.222.222-22',
                'unique_code'           => '2222',
                'role'                  => 'gestor',
                'department'            => 'Recursos Humanos',
                'position'              => 'Gerente de RH',
                'expected_hours_daily'  => 8.0,
                'work_schedule_start'   => '08:00:00',
                'work_schedule_end'     => '17:00:00',
                'extra_hours_balance'   => 5.5,
                'owed_hours_balance'    => 0,
                'active'                => 1,
                'has_face_biometric'    => 1,
                'has_fingerprint_biometric' => 1,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ],
            [
                'name'                  => 'Carlos Desenvolvedor',
                'email'                 => 'carlos.dev@empresateste.com.br',
                'password'              => $this->hashTestPassword(self::TEST_DEV_PASSWORD),
                'cpf'                   => '333.333.333-33',
                'unique_code'           => '3333',
                'role'                  => 'colaborador',
                'department'            => 'TI',
                'position'              => 'Desenvolvedor Pleno',
                'expected_hours_daily'  => 8.0,
                'work_schedule_start'   => '09:00:00',
                'work_schedule_end'     => '18:00:00',
                'extra_hours_balance'   => 2.0,
                'owed_hours_balance'    => 0,
                'active'                => 1,
                'has_face_biometric'    => 0,
                'has_fingerprint_biometric' => 1,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ],
            [
                'name'                  => 'Ana Santos',
                'email'                 => 'ana.santos@empresateste.com.br',
                'password'              => $this->hashTestPassword(self::TEST_ANA_PASSWORD),
                'cpf'                   => '444.444.444-44',
                'unique_code'           => '4444',
                'role'                  => 'colaborador',
                'department'            => 'Vendas',
                'position'              => 'Analista de Vendas',
                'expected_hours_daily'  => 8.0,
                'work_schedule_start'   => '08:00:00',
                'work_schedule_end'     => '17:00:00',
                'extra_hours_balance'   => 0,
                'owed_hours_balance'    => 1.5,
                'active'                => 1,
                'has_face_biometric'    => 1,
                'has_fingerprint_biometric' => 0,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ],
            [
                'name'                  => 'Pedro Oliveira',
                'email'                 => 'pedro.oliveira@empresateste.com.br',
                'password'              => $this->hashTestPassword(self::TEST_PEDRO_PASSWORD),
                'cpf'                   => '555.555.555-55',
                'unique_code'           => '5555',
                'role'                  => 'colaborador',
                'department'            => 'Financeiro',
                'position'              => 'Assistente Financeiro',
                'expected_hours_daily'  => 8.0,
                'work_schedule_start'   => '08:30:00',
                'work_schedule_end'     => '17:30:00',
                'extra_hours_balance'   => 0,
                'owed_hours_balance'    => 0,
                'active'                => 1,
                'has_face_biometric'    => 0,
                'has_fingerprint_biometric' => 0,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('employees')->insertBatch($employees);

        // Get employee IDs for time punches
        $employeeIds = $this->db->table('employees')->select('id, name')->get()->getResultArray();

        // ============================================================================
        // 3. GEOFENCE (Created after employees to use admin as creator)
        // ============================================================================

        $geofenceData = [
            'name'          => 'Sede Principal',
            'description'   => 'Perímetro autorizado da sede da empresa',
            'center_lat'    => -23.550520,  // São Paulo coordinates
            'center_lng'    => -46.633308,
            'radius_meters' => 100,  // 100 meters
            'address'       => 'Rua das Flores, 123 - São Paulo, SP',
            'active'        => 1,
            'color'         => '#4CAF50',
            'created_by'    => $employeeIds[0]['id'], // Admin
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $this->db->table('geofences')->insert($geofenceData);
        $geofenceId = $this->db->insertID();

        // ============================================================================
        // 4. TIME PUNCHES (Last 7 days)
        // ============================================================================

        $timePunches = [];
        $nsrCounter = 1; // Sequential Record Number

        foreach ($employeeIds as $employee) {
            // Create punches for last 7 days
            for ($day = 6; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-{$day} days"));

                // Skip weekends
                if (date('N', strtotime($date)) >= 6) {
                    continue;
                }

                // Morning entry (with some variation)
                $morningEntry = $date . ' ' . sprintf('%02d:%02d:00', 8 + rand(0, 1), rand(0, 59));
                $empId = $employee['id'];
                $nsr = $nsrCounter++;
                $timePunches[] = [
                    'employee_id'       => $empId,
                    'punch_time'        => $morningEntry,
                    'punch_type'        => 'entrada',
                    'method'            => rand(0, 1) ? 'qrcode' : 'codigo',
                    'nsr'               => $nsr,
                    'hash'              => hash('sha256', $empId . $morningEntry . $nsr),
                    'location_lat'      => -23.550520 + (rand(-50, 50) / 10000),
                    'location_lng'      => -46.633308 + (rand(-50, 50) / 10000),
                    'location_accuracy' => rand(5, 20),
                    'within_geofence'   => 1,
                    'geofence_name'     => 'Sede Principal',
                    'user_agent'        => 'Mobile App - Android 13',
                    'ip_address'        => '192.168.1.' . rand(10, 254),
                    'notes'             => NULL,
                    'created_at'        => $morningEntry,
                ];

                // Lunch break start
                $lunchStart = $date . ' 12:' . sprintf('%02d:00', rand(0, 15));
                $nsr = $nsrCounter++;
                $timePunches[] = [
                    'employee_id'       => $empId,
                    'punch_time'        => $lunchStart,
                    'punch_type'        => 'saida',
                    'method'            => 'codigo',
                    'nsr'               => $nsr,
                    'hash'              => hash('sha256', $empId . $lunchStart . $nsr),
                    'location_lat'      => -23.550520 + (rand(-50, 50) / 10000),
                    'location_lng'      => -46.633308 + (rand(-50, 50) / 10000),
                    'location_accuracy' => rand(5, 20),
                    'within_geofence'   => 1,
                    'geofence_name'     => 'Sede Principal',
                    'user_agent'        => 'Mobile App - Android 13',
                    'ip_address'        => '192.168.1.' . rand(10, 254),
                    'notes'             => 'Intervalo almoço',
                    'created_at'        => $lunchStart,
                ];

                // Lunch break end
                $lunchEnd = $date . ' 13:' . sprintf('%02d:00', rand(0, 15));
                $nsr = $nsrCounter++;
                $timePunches[] = [
                    'employee_id'       => $empId,
                    'punch_time'        => $lunchEnd,
                    'punch_type'        => 'entrada',
                    'method'            => 'qrcode',
                    'nsr'               => $nsr,
                    'hash'              => hash('sha256', $empId . $lunchEnd . $nsr),
                    'location_lat'      => -23.550520 + (rand(-50, 50) / 10000),
                    'location_lng'      => -46.633308 + (rand(-50, 50) / 10000),
                    'location_accuracy' => rand(5, 20),
                    'within_geofence'   => 1,
                    'geofence_name'     => 'Sede Principal',
                    'user_agent'        => 'Mobile App - Android 13',
                    'ip_address'        => '192.168.1.' . rand(10, 254),
                    'notes'             => NULL,
                    'created_at'        => $lunchEnd,
                ];

                // Evening exit
                $eveningExit = $date . ' ' . sprintf('%02d:%02d:00', 17 + rand(0, 2), rand(0, 59));
                $nsr = $nsrCounter++;
                $timePunches[] = [
                    'employee_id'       => $empId,
                    'punch_time'        => $eveningExit,
                    'punch_type'        => 'saida',
                    'method'            => rand(0, 1) ? 'facial' : 'qrcode',
                    'nsr'               => $nsr,
                    'hash'              => hash('sha256', $empId . $eveningExit . $nsr),
                    'location_lat'      => -23.550520 + (rand(-50, 50) / 10000),
                    'location_lng'      => -46.633308 + (rand(-50, 50) / 10000),
                    'location_accuracy' => rand(5, 20),
                    'within_geofence'   => 1,
                    'geofence_name'     => 'Sede Principal',
                    'user_agent'        => 'Mobile App - Android 13',
                    'ip_address'        => '192.168.1.' . rand(10, 254),
                    'notes'             => NULL,
                    'created_at'        => $eveningExit,
                ];
            }
        }

        $this->db->table('time_punches')->insertBatch($timePunches);

        // ============================================================================
        // 5. JUSTIFICATIONS
        // ============================================================================

        $justifications = [
            [
                'employee_id'         => $employeeIds[3]['id'], // Ana Santos
                'justification_date'  => date('Y-m-d', strtotime('-3 days')),
                'justification_type'  => 'atraso',
                'category'            => 'outro',
                'reason'              => 'Trânsito intenso na Marginal Tietê',
                'status'              => 'aprovado',
                'approved_by'         => $employeeIds[1]['id'], // Maria Gestora
                'approved_at'         => date('Y-m-d H:i:s', strtotime('-2 days')),
                'submitted_by'        => $employeeIds[3]['id'],
                'created_at'          => date('Y-m-d H:i:s', strtotime('-3 days')),
                'updated_at'          => date('Y-m-d H:i:s', strtotime('-2 days')),
            ],
            [
                'employee_id'         => $employeeIds[4]['id'], // Pedro Oliveira
                'justification_date'  => date('Y-m-d', strtotime('-1 day')),
                'justification_type'  => 'falta',
                'category'            => 'doenca',
                'reason'              => 'Consulta médica agendada',
                'status'              => 'pendente',
                'approved_by'         => NULL,
                'approved_at'         => NULL,
                'submitted_by'        => $employeeIds[4]['id'],
                'created_at'          => date('Y-m-d H:i:s', strtotime('-1 day')),
                'updated_at'          => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
        ];

        $this->db->table('justifications')->insertBatch($justifications);

        // ============================================================================
        // LOG SUMMARY
        // ============================================================================

        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  TEST DATA SEEDER - EXECUTION SUMMARY\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        echo "✓ Companies created:        1\n";
        echo "✓ Geofences created:        1\n";
        echo "✓ Employees created:        " . count($employees) . "\n";
        echo "✓ Time punches created:     " . count($timePunches) . "\n";
        echo "✓ Justifications created:   " . count($justifications) . "\n\n";

        echo "─────────────────────────────────────────────────────────────\n";
        echo "  TEST CREDENTIALS:\n";
        echo "─────────────────────────────────────────────────────────────\n\n";

        echo "Admin:\n";
        echo "  Email:    admin@empresateste.com.br\n";
        echo "  Password: " . self::TEST_ADMIN_PASSWORD . "\n\n";

        echo "Manager:\n";
        echo "  Email:    maria.gestora@empresateste.com.br\n";
        echo "  Password: " . self::TEST_MANAGER_PASSWORD . "\n\n";

        echo "Employee:\n";
        echo "  Email:    carlos.dev@empresateste.com.br\n";
        echo "  Password: " . self::TEST_DEV_PASSWORD . "\n\n";

        echo "═══════════════════════════════════════════════════════════════\n\n";
    }

    private function assertTestingEnvironment(): void
    {
        if (ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('App\\Database\\Seeds\\Testing\\TestDataSeeder is restricted to CI_ENVIRONMENT=testing.');
        }
    }
}

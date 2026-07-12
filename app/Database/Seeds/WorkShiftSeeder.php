<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class WorkShiftSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('work_shifts')) {
            throw new RuntimeException("Tabela obrigatória 'work_shifts' não existe. Execute as migrations antes do WorkShiftSeeder.");
        }

        if (! $this->db->tableExists('employees')) {
            throw new RuntimeException("Tabela obrigatória 'employees' não existe. Execute as migrations antes do WorkShiftSeeder.");
        }

        $adminUser = $this->db->table('employees')
            ->where('role', 'admin')
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow();

        if ($adminUser === null) {
            throw new RuntimeException('Admin user not found. Execute o provisionamento/admin seeder antes do WorkShiftSeeder.');
        }

        $createdBy = $adminUser->id;
        $now = date('Y-m-d H:i:s');
        $builder = $this->db->table('work_shifts');

        $shifts = [
            [
                'name' => 'Manhã',
                'description' => 'Turno da manhã padrão (08:00 - 12:00)',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'color' => '#FFA500',
                'type' => 'morning',
                'break_duration' => 0,
                'active' => true,
                'created_by' => $createdBy,
            ],
            [
                'name' => 'Tarde',
                'description' => 'Turno da tarde padrão (13:00 - 18:00)',
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'color' => '#4169E1',
                'type' => 'afternoon',
                'break_duration' => 0,
                'active' => true,
                'created_by' => $createdBy,
            ],
            [
                'name' => 'Noite',
                'description' => 'Turno da noite (22:00 - 06:00) com intervalo de 1h',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'color' => '#2F4F4F',
                'type' => 'night',
                'break_duration' => 60,
                'active' => true,
                'created_by' => $createdBy,
            ],
            [
                'name' => 'Comercial',
                'description' => 'Horário comercial completo (08:00 - 18:00) com intervalo de 1h',
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'color' => '#228B22',
                'type' => 'custom',
                'break_duration' => 60,
                'active' => true,
                'created_by' => $createdBy,
            ],
        ];

        foreach ($shifts as $shift) {
            $existing = $builder->where('name', $shift['name'])->get()->getRow();
            if ($existing !== null) {
                echo "- Turno já existe: {$shift['name']}\n";
                continue;
            }

            $shift['created_at'] = $now;
            $shift['updated_at'] = $now;
            if (! $builder->insert($shift)) {
                throw new RuntimeException('Falha ao criar turno inicial: ' . $shift['name']);
            }
            echo "✓ Turno criado: {$shift['name']}\n";
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMissingEmployeeColumns extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('employees')) {
            log_message('warning', 'Migration AddMissingEmployeeColumns ignorada: tabela employees ainda não existe.');
            return;
        }

        $columns = [
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'daily_hours' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 8.00,
            ],
            'weekly_hours' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 44.00,
            ],
            'work_start_time' => [
                'type' => 'TIME',
                'default' => '08:00:00',
            ],
            'work_end_time' => [
                'type' => 'TIME',
                'default' => '18:00:00',
            ],
            'lunch_start_time' => [
                'type' => 'TIME',
                'default' => '12:00:00',
            ],
            'lunch_end_time' => [
                'type' => 'TIME',
                'default' => '13:00:00',
            ],
            'work_shift_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'allow_remote_punch' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'require_geolocation' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'employees')) {
                $this->forge->addColumn('employees', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('employees', [
            'birth_date',
            'daily_hours', 
            'weekly_hours',
            'work_start_time',
            'work_end_time',
            'lunch_start_time',
            'lunch_end_time',
            'work_shift_id',
            'allow_remote_punch',
            'require_geolocation',
        ]);
    }
}

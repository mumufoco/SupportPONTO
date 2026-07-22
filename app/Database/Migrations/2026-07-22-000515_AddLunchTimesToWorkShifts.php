<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * work_shifts.lunch_start_time/lunch_end_time nunca existiam na tabela —
 * o model e as views de turno já liam/gravavam esses campos, mas sem a
 * coluna no banco o valor sempre voltava vazio (bug do intervalo do turno).
 */
class AddLunchTimesToWorkShifts extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('work_shifts')) {
            log_message('warning', 'Migration AddLunchTimesToWorkShifts ignorada: tabela work_shifts ainda não existe.');
            return;
        }

        $columns = [
            'lunch_start_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'lunch_end_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'work_shifts')) {
                $this->forge->addColumn('work_shifts', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('work_shifts', ['lunch_start_time', 'lunch_end_time']);
    }
}

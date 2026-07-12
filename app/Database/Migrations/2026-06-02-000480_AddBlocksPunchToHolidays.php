<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBlocksPunchToHolidays extends Migration
{
    public function up(): void
    {
        // Add blocks_punch: when true, punch-in is blocked on this date unless admin overrides
        $this->forge->addColumn('holidays', [
            'blocks_punch' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'recurring',
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
                'after'      => 'name',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('holidays', 'blocks_punch');
        $this->forge->dropColumn('holidays', 'description');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHolidaysTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'date' => [
                'type' => 'DATE',
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'national',
            ],
            'recurring' => [
                'type' => 'INT',
                'constraint' => 1,
                'default' => 0,
            ],
            'active' => [
                'type' => 'INT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('date');
        $this->forge->addKey('type');
        $this->forge->addKey('recurring');
        $this->forge->addKey('active');

        $this->forge->createTable('holidays', true);
    }

    public function down()
    {
        $this->forge->dropTable('holidays', true);
    }
}

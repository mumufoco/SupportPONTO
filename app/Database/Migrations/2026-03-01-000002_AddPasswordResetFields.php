<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPasswordResetFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('employees', [
            'password_reset_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'password',
            ],
            'password_reset_expires' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'password_reset_token',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('employees', 'password_reset_token');
        $this->forge->dropColumn('employees', 'password_reset_expires');
    }
}

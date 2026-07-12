<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLastLoginToEmployees extends Migration
{
    public function up()
    {
        // Add last_login column to employees table if it doesn't exist
        if (!$this->db->fieldExists('last_login', 'employees')) {
            $this->forge->addColumn('employees', [
                'last_login' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'updated_at'
                ]
            ]);
        }
    }

    public function down()
    {
        // Drop last_login column from employees table
        if ($this->db->fieldExists('last_login', 'employees')) {
            $this->forge->dropColumn('employees', 'last_login');
        }
    }
}

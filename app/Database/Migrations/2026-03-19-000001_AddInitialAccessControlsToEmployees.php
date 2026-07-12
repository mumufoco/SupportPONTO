<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInitialAccessControlsToEmployees extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('must_change_password', 'employees')) {
            $this->forge->addColumn('employees', [
                'must_change_password' => [
                    'type'       => 'BOOLEAN',
                    'default'    => false,
                    'null'       => false,
                    'after'      => 'password',
                    'comment'    => 'Força troca de senha no primeiro acesso',
                ],
            ]);
        }

        if (! $this->db->fieldExists('password_changed_at', 'employees')) {
            $this->forge->addColumn('employees', [
                'password_changed_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'after'   => 'must_change_password',
                    'comment' => 'Data da última troca de senha',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('password_changed_at', 'employees')) {
            $this->forge->dropColumn('employees', 'password_changed_at');
        }

        if ($this->db->fieldExists('must_change_password', 'employees')) {
            $this->forge->dropColumn('employees', 'must_change_password');
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBiometricColumnsToEmployees extends Migration
{
    public function up()
    {
        $fields = [
            'has_face_biometric' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'null'       => false,
                'comment'    => 'Indica se o funcionário possui biometria facial cadastrada',
                'after'      => 'active',
            ],
            'has_fingerprint_biometric' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'null'       => false,
                'comment'    => 'Indica se o funcionário possui biometria de digital cadastrada',
                'after'      => 'has_face_biometric',
            ],
        ];

        if (!$this->db->tableExists('employees')) {
            log_message('warning', 'Migration AddBiometricColumnsToEmployees ignorada: tabela employees ainda não existe.');
            return;
        }

        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'employees')) {
                $this->forge->addColumn('employees', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('employees', ['has_face_biometric', 'has_fingerprint_biometric']);
    }
}

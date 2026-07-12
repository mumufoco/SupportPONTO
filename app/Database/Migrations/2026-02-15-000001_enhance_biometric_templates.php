<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enhance Biometric Templates Table.
 *
 * PostgreSQL-first migration for SourceAFIS/fingerprint metadata.
 */
class EnhanceBiometricTemplates extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('biometric_templates')) {
            return;
        }

        $fields = [
            'finger_position' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
                'comment'    => 'Posição do dedo: thumb_right, index_left, etc.',
                'after'      => 'biometric_type',
            ],
            'template_format' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'sourceafis_proprietary',
                'comment'    => 'Formato do template: sourceafis_proprietary, ansi-378, iso-19794-2',
                'after'      => 'template_hash',
            ],
            'quality_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
                'comment'    => 'Qualidade 0-100 (min 30 para cadastro)',
                'after'      => 'enrollment_quality',
            ],
            'capture_method' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'null'       => true,
                'comment'    => 'Método de captura: scanner, image, mobile',
                'after'      => 'quality_score',
            ],
            'capture_device' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Dispositivo usado na captura',
                'after'      => 'capture_method',
            ],
            'algorithm_version' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
                'comment'    => 'Versão do algoritmo SourceAFIS',
                'after'      => 'model_used',
            ],
            'is_active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Template ativo (substitui campo "active")',
                'after'      => 'algorithm_version',
            ],
            'enrolled_at' => [
                'type'    => 'TIMESTAMP',
                'null'    => true,
                'comment' => 'Data/hora do cadastro',
                'after'   => 'is_active',
            ],
            'last_used_at' => [
                'type'    => 'TIMESTAMP',
                'null'    => true,
                'comment' => 'Última autenticação bem-sucedida',
                'after'   => 'enrolled_at',
            ],
            'usage_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Contador de autenticações',
                'after'      => 'last_used_at',
            ],
            'metadata' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'JSON com metadados adicionais',
                'after'   => 'usage_count',
            ],
        ];

        foreach ($fields as $fieldName => $fieldConfig) {
            if (! $this->db->fieldExists($fieldName, 'biometric_templates')) {
                $this->forge->addColumn('biometric_templates', [$fieldName => $fieldConfig]);
            }
        }

        foreach ([
            'idx_biometric_active_type' => 'is_active, biometric_type',
            'idx_biometric_employee_active' => 'employee_id, is_active',
            'idx_biometric_finger_position' => 'employee_id, finger_position',
            'idx_biometric_quality' => 'quality_score',
        ] as $name => $columns) {
            $this->db->query("CREATE INDEX IF NOT EXISTS {$name} ON biometric_templates ({$columns})");
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('biometric_templates')) {
            return;
        }

        foreach ([
            'idx_biometric_quality',
            'idx_biometric_finger_position',
            'idx_biometric_employee_active',
            'idx_biometric_active_type',
        ] as $name) {
            $this->db->query("DROP INDEX IF EXISTS {$name}");
        }

        foreach ([
            'finger_position',
            'template_format',
            'quality_score',
            'capture_method',
            'capture_device',
            'algorithm_version',
            'is_active',
            'enrolled_at',
            'last_used_at',
            'usage_count',
            'metadata',
        ] as $field) {
            if ($this->db->fieldExists($field, 'biometric_templates')) {
                $this->forge->dropColumn('biometric_templates', $field);
            }
        }
    }
}

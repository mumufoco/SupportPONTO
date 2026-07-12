<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * QUA-05 FIX: Unifica os campos 'active' e 'is_active' da tabela employees.
 *
 * Problema: ambos os campos coexistiam, causando inconsistência silenciosa.
 * Solução: 'active' é o campo canônico. Se is_active existir na tabela,
 * migra os valores e remove a coluna.
 */
class UnifyActiveColumnEmployees extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        // Verificar se ambas as colunas existem
        $fields = $db->getFieldNames('employees');

        $hasActive   = in_array('active', $fields, true);
        $hasIsActive = in_array('is_active', $fields, true);

        if ($hasIsActive && $hasActive) {
            // Migrar valores de is_active para active onde active é NULL ou 0
            $db->query(
                'UPDATE employees SET active = is_active WHERE active IS NULL OR active = 0'
            );

            // Remover coluna redundante
            $this->forge->dropColumn('employees', 'is_active');

        } elseif ($hasIsActive && !$hasActive) {
            // Caso raro: só is_active existe — renomear para active
            $this->forge->modifyColumn('employees', [
                'is_active' => [
                    'name'       => 'active',
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'null'       => false,
                ],
            ]);
        }
        // Se apenas 'active' existe: nada a fazer
    }

    public function down(): void
    {
        $db     = \Config\Database::connect();
        $fields = $db->getFieldNames('employees');

        if (!in_array('active', $fields, true)) {
            return;
        }

        // Rollback idempotente: recria a coluna apenas para compatibilidade histórica,
        // sem reintroduzi-la como campo canônico da aplicação.
        if (!in_array('is_active', $fields, true)) {
            $this->forge->addColumn('employees', [
                'is_active' => [
                    'type'       => 'BOOLEAN',
                    'default'    => true,
                    'null'       => false,
                    'after'      => 'active',
                    'comment'    => 'Compatibilidade temporária em rollback. O campo canônico permanece employees.active.',
                ],
            ]);
        }

        $db->query('UPDATE employees SET is_active = active');
    }
}

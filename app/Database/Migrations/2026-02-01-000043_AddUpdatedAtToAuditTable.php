<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Compatibilidade legada.
 *
 * A aplicação atual usa audit_logs imutável e não atualiza registros de
 * auditoria. Esta migration existia para uma tabela antiga `audit`; em bases
 * novas ela deve ser no-op para não recriar/depender de tabela paralela.
 */
class AddUpdatedAtToAuditTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('audit')) {
            return;
        }

        $columns = $this->db->getFieldNames('audit');
        if (! in_array('updated_at', $columns, true)) {
            $this->forge->addColumn('audit', [
                'updated_at' => [
                    'type' => 'TIMESTAMP',
                    'default' => 'CURRENT_TIMESTAMP',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('audit') && in_array('updated_at', $this->db->getFieldNames('audit'), true)) {
            $this->forge->dropColumn('audit', 'updated_at');
        }
    }
}

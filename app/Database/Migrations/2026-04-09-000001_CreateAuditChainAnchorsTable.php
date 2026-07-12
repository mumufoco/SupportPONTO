<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditChainAnchorsTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('audit_chain_anchors')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'cutoff_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'anchor_checksum' => [
                    'type' => 'CHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('cutoff_at');
            $this->forge->createTable('audit_chain_anchors');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_chain_anchors', true);
    }
}

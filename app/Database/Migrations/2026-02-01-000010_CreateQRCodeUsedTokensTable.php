<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQRCodeUsedTokensTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'jti' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'used_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('jti');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('used_at');
        
        $this->forge->createTable('qrcode_used_tokens', true);

        $this->db->query('CREATE INDEX idx_qrcode_tokens_cleanup ON qrcode_used_tokens (used_at)');
    }

    public function down()
    {
        $this->forge->dropTable('qrcode_used_tokens', true);
    }
}

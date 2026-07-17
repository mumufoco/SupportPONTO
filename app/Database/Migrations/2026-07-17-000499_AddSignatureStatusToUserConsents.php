<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSignatureStatusToUserConsents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('user_consents', [
            'supportcheck_document_id' => [
                'type'    => 'VARCHAR',
                'constraint' => 120,
                'null'    => true,
                'after'   => 'synced_to_supportcheck_at',
                'comment' => 'ID do documento no SupportCHECK, devolvido no callback de status de assinatura',
            ],
            'supportcheck_internal_code' => [
                'type'    => 'VARCHAR',
                'constraint' => 120,
                'null'    => true,
                'after'   => 'supportcheck_document_id',
            ],
            'signature_status' => [
                'type'    => 'VARCHAR',
                'constraint' => 30,
                'null'    => true,
                'after'   => 'supportcheck_internal_code',
                'comment' => 'pending_signature, sent_to_signature, viewed, signed, refused, expired, cancelled, failed, synced, sync_failed',
            ],
            'signature_provider' => [
                'type'    => 'VARCHAR',
                'constraint' => 60,
                'null'    => true,
                'after'   => 'signature_status',
            ],
            'provider_document_id' => [
                'type'    => 'VARCHAR',
                'constraint' => 120,
                'null'    => true,
                'after'   => 'signature_provider',
            ],
            'provider_signature_id' => [
                'type'    => 'VARCHAR',
                'constraint' => 120,
                'null'    => true,
                'after'   => 'provider_document_id',
            ],
            'signed_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'after'   => 'provider_signature_id',
            ],
            'signed_file_reference' => [
                'type'    => 'VARCHAR',
                'constraint' => 255,
                'null'    => true,
                'after'   => 'signed_at',
            ],
            'signed_file_hash' => [
                'type'    => 'VARCHAR',
                'constraint' => 128,
                'null'    => true,
                'after'   => 'signed_file_reference',
            ],
            'audit_status' => [
                'type'    => 'VARCHAR',
                'constraint' => 30,
                'null'    => true,
                'after'   => 'signed_file_hash',
            ],
            'signature_sync_message' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'audit_status',
            ],
            'signature_updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'after'   => 'signature_sync_message',
                'comment' => 'Quando o SupportPONTO recebeu por ultimo uma atualizacao de status de assinatura do SupportCHECK',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('user_consents', [
            'supportcheck_document_id',
            'supportcheck_internal_code',
            'signature_status',
            'signature_provider',
            'provider_document_id',
            'provider_signature_id',
            'signed_at',
            'signed_file_reference',
            'signed_file_hash',
            'audit_status',
            'signature_sync_message',
            'signature_updated_at',
        ]);
    }
}

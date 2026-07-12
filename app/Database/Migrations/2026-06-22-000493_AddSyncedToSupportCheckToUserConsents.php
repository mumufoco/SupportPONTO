<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSyncedToSupportCheckToUserConsents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('user_consents', [
            'synced_to_supportcheck_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'evidence_hash',
                'comment' => 'Quando este consentimento foi enviado ao SupportCHECK como termo para assinatura',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('user_consents', 'synced_to_supportcheck_at');
    }
}

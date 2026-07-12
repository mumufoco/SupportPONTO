<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Push Subscriptions Migration
 *
 * Stores browser push notification subscriptions for employees
 */
class CreatePushSubscriptionsTable extends Migration
{
    public function up()
    {
        // Push Subscriptions Table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'endpoint' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'comment'    => 'Push service endpoint URL',
            ],
            'public_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'P256DH public key',
            ],
            'auth_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Auth secret',
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Browser user agent',
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Subscription is active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('endpoint');
        $this->forge->addKey(['employee_id', 'endpoint'], false, true); // Unique constraint
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_subscriptions');
    }

    public function down()
    {
        $this->forge->dropTable('push_subscriptions');
    }
}

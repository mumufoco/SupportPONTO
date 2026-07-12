<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create Push Notification Tokens Table
 *
 * Stores FCM device tokens for push notifications
 */
class CreatePushNotificationTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'comment' => 'Employee who owns this device',
            ],
            'device_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'comment' => 'FCM device registration token',
            ],
            'platform' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'default' => 'android',
                'comment' => 'Device platform',
            ],
            'device_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'comment' => 'Friendly device name',
            ],
            'is_valid' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'comment' => 'Token validity status',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token registration time',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token last update time',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('device_token');
        $this->forge->addKey(['employee_id', 'is_valid']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_notification_tokens');

        // Add unique constraint on device_token (PostgreSQL syntax)
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_push_tokens_device ON push_notification_tokens(device_token)');

        // Add index for cleanup queries (PostgreSQL syntax)
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_push_tokens_valid ON push_notification_tokens(is_valid)');
    }

    public function down()
    {
        // Drop indexes (PostgreSQL syntax)
        $this->db->query('DROP INDEX IF EXISTS idx_push_tokens_device');
        $this->db->query('DROP INDEX IF EXISTS idx_push_tokens_valid');

        // Drop table
        $this->forge->dropTable('push_notification_tokens', true);
    }
}

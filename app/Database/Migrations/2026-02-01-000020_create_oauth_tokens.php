<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create OAuth Tokens Tables
 *
 * Creates tables for OAuth 2.0 access tokens and refresh tokens
 */
class CreateOAuthTokens extends Migration
{
    public function up()
    {
        // OAuth Access Tokens Table
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'comment' => 'Employee who owns this token',
            ],
            'token_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'comment' => 'SHA-256 hash of the access token',
            ],
            'device_fingerprint' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'comment' => 'Device fingerprint for security',
            ],
            'scopes' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Token scopes (JSON array)',
            ],
            'revoked' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'comment' => 'Token revoked status',
            ],
            'expires_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token expiration time',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token creation time',
            ],
            'last_used_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Last time token was used',
            ],
            'revoked_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Token revocation time',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('token_hash');
        $this->forge->addKey(['revoked', 'expires_at']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('oauth_access_tokens');

        // Add indexes for performance (PostgreSQL syntax)
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_oauth_access_tokens_lookup ON oauth_access_tokens(token_hash, revoked, expires_at)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_oauth_access_tokens_employee ON oauth_access_tokens(employee_id, revoked)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_oauth_access_tokens_device ON oauth_access_tokens(device_fingerprint, revoked)');

        // OAuth Refresh Tokens Table
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'comment' => 'Employee who owns this token',
            ],
            'access_token_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'comment' => 'Associated access token',
            ],
            'token_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'comment' => 'SHA-256 hash of the refresh token',
            ],
            'device_fingerprint' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'comment' => 'Device fingerprint for security',
            ],
            'revoked' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'comment' => 'Token revoked status',
            ],
            'expires_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token expiration time',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'comment' => 'Token creation time',
            ],
            'revoked_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Token revocation time',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('access_token_id');
        $this->forge->addKey('token_hash');
        $this->forge->addKey(['revoked', 'expires_at']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('access_token_id', 'oauth_access_tokens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('oauth_refresh_tokens');

        // Add indexes for performance (PostgreSQL syntax)
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_lookup ON oauth_refresh_tokens(token_hash, revoked, expires_at)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_employee ON oauth_refresh_tokens(employee_id, revoked)');
    }

    public function down()
    {
        // Drop indexes first (PostgreSQL syntax)
        $this->db->query('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_lookup');
        $this->db->query('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_employee');
        $this->db->query('DROP INDEX IF EXISTS idx_oauth_access_tokens_lookup');
        $this->db->query('DROP INDEX IF EXISTS idx_oauth_access_tokens_employee');
        $this->db->query('DROP INDEX IF EXISTS idx_oauth_access_tokens_device');

        // Drop tables
        $this->forge->dropTable('oauth_refresh_tokens', true);
        $this->forge->dropTable('oauth_access_tokens', true);
    }
}

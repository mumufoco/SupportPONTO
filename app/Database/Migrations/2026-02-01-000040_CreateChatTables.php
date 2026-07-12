<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create Chat Tables Migration
 *
 * Creates tables for WebSocket chat functionality
 */
class CreateChatTables extends Migration
{
    public function up()
    {
        // Chat Rooms Table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'private',
            ],
            'department' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Department for department-wide chats',
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
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
        $this->forge->addKey('type');
        $this->forge->addKey('department');
        $this->forge->addKey('created_by');
        $this->forge->addForeignKey('created_by', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_rooms');

        // Chat Room Members Table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'room_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'member',
            ],
            'last_read_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'Last time member read messages',
            ],
            'joined_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('room_id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey(['room_id', 'employee_id'], false, true); // Unique combination
        $this->forge->addForeignKey('room_id', 'chat_rooms', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_room_members');

        // Chat Messages Table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'room_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'sender_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'message' => [
                'type' => 'TEXT',
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'text',
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Path to uploaded file',
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'file_size' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reply_to' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Message ID being replied to',
            ],
            'edited_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('room_id');
        $this->forge->addKey('sender_id');
        $this->forge->addKey('created_at');
        $this->forge->addKey('reply_to');
        $this->forge->addForeignKey('room_id', 'chat_rooms', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sender_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reply_to', 'chat_messages', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('chat_messages');

        // Chat Message Reactions Table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'message_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'emoji' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'comment'    => 'Emoji reaction (👍, ❤️, etc.)',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('message_id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey(['message_id', 'employee_id', 'emoji'], false, true); // Unique combination
        $this->forge->addForeignKey('message_id', 'chat_messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_message_reactions');

        // Chat Online Users Table (for tracking online status)
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
            'connection_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'comment'    => 'WebSocket connection ID',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'online',
            ],
            'last_activity' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('connection_id', false, true); // Unique (not primary key)
        $this->forge->addKey('status');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_online_users');
    }

    public function down()
    {
        $this->forge->dropTable('chat_message_reactions', true);
        $this->forge->dropTable('chat_messages', true);
        $this->forge->dropTable('chat_room_members', true);
        $this->forge->dropTable('chat_online_users', true);
        $this->forge->dropTable('chat_rooms', true);
    }
}

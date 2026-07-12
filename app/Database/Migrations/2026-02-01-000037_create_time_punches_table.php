<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTimePunchesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('time_punches')) {
            log_message('info', 'Migration time_punches ignorada: tabela já existe.');
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'null'       => false,
                'comment'    => 'FK para employees',
            ],
            'punch_time' => [
                'type'    => 'TIMESTAMP',
                'null'    => false,
                'comment' => 'Data e hora da marcação',
            ],
            'punch_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => false,
                'comment'    => 'Tipo de marcação (entrada, saida, etc.)',
            ],
            'latitude' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => true,
                'comment'    => 'Latitude da marcação GPS',
            ],
            'longitude' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
                'comment'    => 'Longitude da marcação GPS',
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Endereço da marcação',
            ],
            'device_info' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Informações do dispositivo',
            ],
            'photo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Caminho da foto da marcação',
            ],
            'validation_method' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'manual',
                'comment'    => 'Método de validação (manual, qrcode, facial, etc.)',
            ],
            'is_valid' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'comment' => 'Se a marcação é válida',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Observações adicionais',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'punch_time']);
        $this->forge->addKey('punch_time');
        $this->forge->addKey(['employee_id', 'punch_type']);

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('time_punches', true);
    }

    public function down()
    {
        $this->forge->dropTable('time_punches', true);
    }
}

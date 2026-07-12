<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeofencesTable extends Migration
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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'comment'    => 'Nome identificador da cerca virtual',
            ],
            'description' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Descrição da localização',
            ],
            'center_lat' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'comment'    => 'Latitude do centro da cerca',
            ],
            'center_lng' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'comment'    => 'Longitude do centro da cerca',
            ],
            'radius_meters' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 100,
                'comment'    => 'Raio da cerca em metros',
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Endereço aproximado (geocoding reverso)',
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Cerca virtual ativa',
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => '7',
                'default'    => '#3388ff',
                'comment'    => 'Cor hexadecimal para exibição no mapa',
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'FK para employees (admin que criou)',
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
        $this->forge->addKey('active');
        $this->forge->addKey(['center_lat', 'center_lng']);

        $this->forge->addForeignKey('created_by', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('geofences');
    }

    public function down()
    {
        $this->forge->dropTable('geofences');
    }
}

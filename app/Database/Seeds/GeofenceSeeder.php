<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class GeofenceSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('geofences')) {
            throw new RuntimeException("Tabela obrigatória 'geofences' não existe. Execute as migrations antes do GeofenceSeeder.");
        }

        if (! $this->db->tableExists('employees')) {
            throw new RuntimeException("Tabela obrigatória 'employees' não existe. Execute as migrations antes do GeofenceSeeder.");
        }

        $db = \Config\Database::connect();
        $builder = $db->table('geofences');

        $adminUser = $db->table('employees')
            ->where('role', 'admin')
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow();

        if (! $adminUser) {
            throw new RuntimeException('Admin user not found. Execute o provisionamento/admin seeder antes do GeofenceSeeder.');
        }

        $geofences = [
            [
                'name'          => 'Sede - Escritório Principal',
                'description'   => 'Cerca virtual da sede da empresa',
                'center_lat'    => -23.5505,
                'center_lng'    => -46.6333,
                'radius_meters' => 100,
                'address'       => 'Praça da Sé - Centro Histórico de São Paulo, São Paulo - SP',
                'active'        => true,
                'color'         => '#3388ff',
                'created_by'    => $adminUser->id,
            ],
            [
                'name'          => 'Filial - Zona Sul',
                'description'   => 'Cerca virtual da filial zona sul',
                'center_lat'    => -23.6236,
                'center_lng'    => -46.6997,
                'radius_meters' => 150,
                'address'       => 'Av. Roque Petroni Júnior - Morumbi, São Paulo - SP',
                'active'        => false,
                'color'         => '#ff6b6b',
                'created_by'    => $adminUser->id,
            ],
            [
                'name'          => 'Depósito - Zona Leste',
                'description'   => 'Cerca virtual do depósito',
                'center_lat'    => -23.5418,
                'center_lng'    => -46.5733,
                'radius_meters' => 200,
                'address'       => 'Praça Silvio Romero - Tatuapé, São Paulo - SP',
                'active'        => false,
                'color'         => '#51cf66',
                'created_by'    => $adminUser->id,
            ],
        ];

        $insertedCount = 0;
        foreach ($geofences as $geofence) {
            $existing = $builder->where('name', $geofence['name'])->get()->getRow();

            if (! $existing) {
                $geofence['created_at'] = date('Y-m-d H:i:s');
                $geofence['updated_at'] = date('Y-m-d H:i:s');
                if (! $builder->insert($geofence)) {
                    throw new RuntimeException("Falha ao criar geofence inicial: {$geofence['name']}");
                }
                $insertedCount++;
                echo "   ✓ Geofence '{$geofence['name']}' created\n";
            } else {
                echo "   - Geofence '{$geofence['name']}' already exists\n";
            }
        }

        echo "\n✅ Geofences seeded successfully!\n";
        echo '   Total geofences: ' . count($geofences) . "\n";
        echo "   New geofences inserted: {$insertedCount}\n";
    }
}

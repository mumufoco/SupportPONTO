<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /** @var array<int, array{class: string, label: string, critical: bool}> */
    private array $seeders = [
        ['class' => 'AdminUserSeeder', 'label' => 'Admin User', 'critical' => true],
        ['class' => 'AuthGroupsSeeder', 'label' => 'Auth Groups', 'critical' => true],
        ['class' => 'SettingsSeeder', 'label' => 'Settings', 'critical' => true],
        ['class' => 'BiometricSettingsSeeder', 'label' => 'Biometric Settings', 'critical' => true],
        ['class' => 'WorkShiftSeeder', 'label' => 'Work Shifts', 'critical' => false],
        ['class' => 'GeofenceSeeder', 'label' => 'Geofences', 'critical' => false],
        ['class' => 'ContractTypeSeeder', 'label' => 'Contract Types', 'critical' => false],
    ];

    public function run()
    {
        echo "\n";
        echo "========================================\n";
        echo "  DATABASE SEEDING - PONTO ELETRÔNICO  \n";
        echo "========================================\n\n";

        $warnings = [];

        foreach ($this->seeders as $index => $definition) {
            $position = $index + 1;
            echo $position . "️⃣  Seeding {$definition['label']}...\n";
            echo "----------------------------------------\n";

            try {
                $this->call($definition['class']);
            } catch (Throwable $e) {
                if ($definition['critical']) {
                    throw $e;
                }

                $warnings[] = sprintf('%s: %s', $definition['class'], $e->getMessage());
                echo "⚠️  Seeder opcional '{$definition['class']}' falhou: {$e->getMessage()}\n";
            }

            echo "\n";
        }

        if ($warnings !== []) {
            echo "Warnings durante o seeding opcional:\n";
            foreach ($warnings as $warning) {
                echo " - {$warning}\n";
            }
            echo "\n";
        }

        echo "========================================\n";
        echo "  SEEDING PROCESS COMPLETED SUCCESSFULLY\n";
        echo "========================================\n\n";
    }
}

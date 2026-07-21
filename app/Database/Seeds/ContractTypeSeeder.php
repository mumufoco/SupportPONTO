<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Os 4 tipos de contrato padrão só eram inseridos uma vez, dentro da
 * migration 2026-07-21-000504 (up()). Isso funciona numa instalação nova,
 * mas se alguém truncar as tabelas depois (reset de ambiente) para rodar
 * DatabaseSeeder de novo, a migration já aparece como aplicada e não
 * insere nada de novo -- contract_types fica vazio. Este seeder cobre
 * exatamente esse caso, com inserts idempotentes (mesmo padrão de
 * WorkShiftSeeder/GeofenceSeeder).
 */
class ContractTypeSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('contract_types')) {
            throw new RuntimeException("Tabela obrigatória 'contract_types' não existe. Execute as migrations antes do ContractTypeSeeder.");
        }

        $now = date('Y-m-d H:i:s');
        $builder = $this->db->table('contract_types');

        // Nomes seguem os mesmos 4 valores fixos que a view antiga gravava
        // em employees.tipo_contrato (varchar por nome, não FK).
        $types = [
            ['name' => 'CLT', 'description' => null],
            ['name' => 'temporario', 'description' => 'Temporário'],
            ['name' => 'estagio', 'description' => 'Estágio'],
            ['name' => 'terceirizado', 'description' => 'Terceirizado'],
        ];

        foreach ($types as $type) {
            $existing = $builder->where('name', $type['name'])->get()->getRow();
            if ($existing !== null) {
                echo "- Tipo de contrato já existe: {$type['name']}\n";
                continue;
            }

            $type['active'] = true;
            $type['created_at'] = $now;
            $type['updated_at'] = $now;
            if (! $builder->insert($type)) {
                throw new RuntimeException('Falha ao criar tipo de contrato inicial: ' . $type['name']);
            }
            echo "✓ Tipo de contrato criado: {$type['name']}\n";
        }
    }
}

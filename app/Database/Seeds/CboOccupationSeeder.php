<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Carrega a tabela de referência da CBO (Classificação Brasileira de
 * Ocupações, MTE) a partir de data/cbo_occupations.csv -- dataset público
 * (datasets-br/cbo no GitHub, domínio público) com os 2445 códigos de
 * ocupação oficiais (nível "Ocupação", ex: 8485-05).
 *
 * Idempotência por população da tabela como um todo (não linha a linha):
 * são ~2445 registros imutáveis de dado de referência, então 2445
 * SELECT+INSERT individuais seria muito mais lento sem trazer benefício --
 * mesma lição de BackfillContractTypesSeedData (dado de referência em
 * massa é tudo-ou-nada, não upsert por linha).
 */
class CboOccupationSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('cbo_occupations')) {
            throw new RuntimeException("Tabela obrigatória 'cbo_occupations' não existe. Execute as migrations antes do CboOccupationSeeder.");
        }

        $builder = $this->db->table('cbo_occupations');
        $existing = (int) $builder->countAllResults();
        if ($existing > 0) {
            echo "- Tabela cbo_occupations já populada ({$existing} registros), nada a fazer.\n";
            return;
        }

        $csvPath = __DIR__ . '/data/cbo_occupations.csv';
        if (! is_file($csvPath)) {
            throw new RuntimeException("Arquivo de dados não encontrado: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir {$csvPath}");
        }

        $header = fgetcsv($handle);
        if ($header !== ['code', 'title']) {
            fclose($handle);
            throw new RuntimeException('Cabeçalho inesperado em cbo_occupations.csv: ' . implode(',', (array) $header));
        }

        $now = date('Y-m-d H:i:s');
        $batch = [];
        $total = 0;
        $chunkSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== 2 || trim((string) $row[0]) === '') {
                continue;
            }

            $batch[] = [
                'code'       => trim($row[0]),
                'title'      => trim($row[1]),
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $chunkSize) {
                $this->db->table('cbo_occupations')->insertBatch($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->db->table('cbo_occupations')->insertBatch($batch);
            $total += count($batch);
        }

        fclose($handle);

        echo "✓ {$total} códigos CBO carregados em cbo_occupations.\n";
    }
}

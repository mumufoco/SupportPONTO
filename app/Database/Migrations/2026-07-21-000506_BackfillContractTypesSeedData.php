<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfill dos 4 tipos de contrato padrão. A migration 000504 já tentava
 * inserir esses registros, mas usava insertBatch() com linhas de colunas
 * diferentes entre si (a primeira sem 'description', as demais com) -- em
 * produção (DBDebug desligado) isso falhou silenciosamente, deixando a
 * tabela criada mas vazia. 000504 já foi corrigida para instalações novas;
 * esta migration só preenche quem já rodou a versão com o bug.
 */
class BackfillContractTypesSeedData extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('contract_types')) {
            return;
        }

        $existing = $this->db->table('contract_types')->countAllResults();
        if ($existing > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('contract_types')->insertBatch([
            ['name' => 'CLT', 'description' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'temporario', 'description' => 'Temporário', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'estagio', 'description' => 'Estágio', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'terceirizado', 'description' => 'Terceirizado', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Não reverte dados -- reverter a criação da tabela (000504) já cobre isso.
    }
}

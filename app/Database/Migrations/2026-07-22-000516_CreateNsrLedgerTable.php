<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rede de segurança contra duplicidade de NSR entre as 5 tabelas que compartilham
 * a mesma sequência canônica (time_punches, clock_adjustments, rep_availability_events,
 * employee_record_events, company_record_events).
 *
 * O contador `nsr_counter` (2026-03-26-000001) é atômico por si só, mas nada impede
 * que ele seja reinicializado por engano (ex.: recriação manual da tabela numa
 * restauração/reset de ambiente) e volte a emitir um NSR já persistido em alguma das
 * tabelas — foi exatamente isso que aconteceu neste ambiente: employee_record_events
 * NSR=1 (2026-07-17) e rep_availability_events NSR=1 (2026-07-21) são o MESMO número
 * em dois registros imutáveis diferentes.
 *
 * `nsr_ledger` adiciona uma restrição de unicidade GLOBAL: cada NSR emitido é
 * inserido aqui (por NsrGeneratorService::next(), na mesma operação que o gera).
 * Se o contador algum dia voltar a emitir um número repetido, a constraint UNIQUE
 * desta tabela bloqueia o insert e o gerador se autocorrige avançando o contador —
 * nunca falha silenciosamente com uma duplicata gravada.
 */
class CreateNsrLedgerTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'nsr' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
                'comment'    => 'Tabela/evento que consumiu este NSR — apenas para diagnóstico.',
            ],
            'issued_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('nsr');
        $this->forge->createTable('nsr_ledger', true);

        // Backfill com todo NSR já persistido nas 5 tabelas canônicas. ON CONFLICT
        // DO NOTHING é proposital: se houver uma duplicata pré-existente (como o
        // NSR=1 citado acima), o backfill preserva a PRIMEIRA ocorrência encontrada
        // e ignora silenciosamente a segunda — a migração não pode (nem deve) apagar
        // ou alterar registros imutáveis para "resolver" a duplicata histórica.
        $tables = [
            'time_punches'            => 'created_at',
            'clock_adjustments'       => 'created_at',
            'rep_availability_events' => 'created_at',
            'employee_record_events'  => 'created_at',
            'company_record_events'   => 'created_at',
        ];

        foreach ($tables as $table => $timestampColumn) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists('nsr', $table)) {
                continue;
            }

            $this->db->query(
                "INSERT INTO nsr_ledger (nsr, source, issued_at)
                 SELECT nsr, ?, {$timestampColumn} FROM {$table} WHERE nsr IS NOT NULL
                 ON CONFLICT (nsr) DO NOTHING",
                [$table]
            );
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('nsr_ledger', true);
    }
}

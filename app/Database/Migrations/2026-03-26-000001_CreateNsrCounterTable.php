<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ARQ-01 FIX: Tabela dedicada de contador NSR com atomicidade garantida.
 *
 * Substitui SELECT MAX(nsr) + transação não-atômica por UPDATE atômico
 * com SELECT ... FOR UPDATE, eliminando a race condition entre workers FPM.
 *
 * Portaria MTE 671/2021 exige sequencialidade sem lacunas e sem duplicidades.
 */
class CreateNsrCounterTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'TINYINT',
                'constraint'     => 1,
                'unsigned'       => true,
            ],
            'value' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => 'Último NSR emitido. Incrementado atomicamente via FOR UPDATE.',
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'comment' => 'Timestamp da última atualização (auditoria).',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('nsr_counter', true);

        // Inicializa o contador com o maior NSR já existente em QUALQUER tabela que
        // compartilhe a sequência canônica — não apenas time_punches. Checar só
        // time_punches aqui já causou um contador reinicializado atrás do maior NSR
        // realmente persistido (ver CreateNsrLedgerTable), sempre que esta tabela
        // precisar ser recriada num ambiente que já tinha as demais tabelas com dados.
        $db = \Config\Database::connect();

        $initialValue = 0;
        foreach (['time_punches', 'clock_adjustments', 'rep_availability_events', 'employee_record_events', 'company_record_events'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('nsr', $table)) {
                continue;
            }

            $result = $db->table($table)->selectMax('nsr')->get();
            $maxRow = $result ? $result->getRow() : null;
            $tableMax = ($maxRow && $maxRow->nsr) ? (int) $maxRow->nsr : 0;
            $initialValue = max($initialValue, $tableMax);
        }

        $db->table('nsr_counter')->insert([
            'id'         => 1,
            'value'      => $initialValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('nsr_counter', true);
    }
}

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

        // Inicializa o contador com o maior NSR existente na tabela de punches
        // para não reiniciar do zero em bancos que já têm registros
        $db = \Config\Database::connect();

        $initialValue = 0;
        if ($db->fieldExists('nsr', 'time_punches')) {
            $result       = $db->table('time_punches')->selectMax('nsr')->get();
            $maxRow       = $result ? $result->getRow() : null;
            $initialValue = ($maxRow && $maxRow->nsr) ? (int) $maxRow->nsr : 0;
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

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabela de declarações de ajuste de relógio do REP-P (SupportPONTO).
 *
 * Alimenta o registro tipo "4" do AFD (Portaria MTE 671/2021 — "Ajuste do relógio"), que exige
 * NSR canônico, data/hora antes e depois do ajuste, e o CPF do responsável pela alteração.
 *
 * Diferente de REP-C/REP-A (terminais físicos cujo relógio é ajustado fisicamente, gerando o
 * evento automaticamente), o "relógio" de um REP-P é o relógio do servidor onde o sistema roda.
 * Mudanças nele são raras e tipicamente conscientes (migração de servidor, correção manual de
 * fuso/horário) — por isso este evento é DECLARADO por um administrador, e não detectado
 * automaticamente. Cada declaração consome um NSR da mesma sequência canônica usada pelas
 * marcações de ponto, preservando a ordenação exigida pelo leiaute do AFD.
 */
class CreateClockAdjustmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nsr' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'NSR canônico — mesma sequência atômica de time_punches.nsr (NsrGeneratorService)',
            ],
            'previous_datetime' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Data e hora do relógio ANTES do ajuste',
            ],
            'adjusted_datetime' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Data e hora do relógio APÓS o ajuste',
            ],
            'responsible_cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => false,
                'comment'    => 'CPF do responsável pela alteração (campo obrigatório no registro tipo 4 do AFD)',
            ],
            'declared_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
                'comment'  => 'employees.id do administrador que registrou a declaração (NOTA: a FK real é '
                    . 'corrigida para employees(id) pela migração 2026-06-07-000488 — ver seu docblock '
                    . 'para o porquê de não ser users(id))',
            ],
            'reason' => [
                'type'    => 'TEXT',
                'null'    => false,
                'comment' => 'Justificativa do ajuste (ex.: migração de servidor, correção de fuso horário)',
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'SHA-256 do registro — evidência de integridade/imutabilidade, no mesmo espírito de time_punches',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nsr');
        $this->forge->addKey('created_at');
        // NOTA: a FK abaixo é corrigida em seguida pela migração 2026-06-07-000488
        // (FixClockAdjustmentsDeclaredByFk) para apontar para `employees(id)` — o SupportPONTO
        // resolve o "usuário logado" via EmployeeModel, e a tabela `users` do CI Shield está
        // vazia em produção. Mantida aqui por fidelidade ao histórico de migrações já aplicado.
        $this->forge->addForeignKey('declared_by', 'users', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('clock_adjustments', true);

        // Imutabilidade: bloqueia UPDATE e DELETE em nível de banco — declarações de ajuste de
        // relógio compõem o AFD e não podem ser alteradas/removidas após registradas, no mesmo
        // espírito de proteção aplicado a time_punches e audit_logs.
        $this->db->query(
            'CREATE OR REPLACE FUNCTION clock_adjustments_block_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION \'clock_adjustments é imutável: % não é permitido (id=%)\', TG_OP, OLD.id;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;'
        );
        $this->db->query(
            'CREATE TRIGGER trg_clock_adjustments_block_update
                BEFORE UPDATE ON clock_adjustments
                FOR EACH ROW EXECUTE FUNCTION clock_adjustments_block_mutation();'
        );
        $this->db->query(
            'CREATE TRIGGER trg_clock_adjustments_block_delete
                BEFORE DELETE ON clock_adjustments
                FOR EACH ROW EXECUTE FUNCTION clock_adjustments_block_mutation();'
        );
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_clock_adjustments_block_update ON clock_adjustments;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_clock_adjustments_block_delete ON clock_adjustments;');
        $this->db->query('DROP FUNCTION IF EXISTS clock_adjustments_block_mutation();');
        $this->forge->dropTable('clock_adjustments', true);
    }
}

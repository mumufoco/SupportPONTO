<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabela de suporte ao registro tipo "5" do AFD — "Inclusão/Alteração/Exclusão de
 * empregados no REP" (Portaria MTE 671/2021), leiaute de 118 bytes COM CRC-16:
 *   NSR(9) + tipo"5"(1) + DH(24) + tipo de operação(1: "I"/"A"/"E") +
 *   CPF do empregado(12) + nome(52) + demais dados de identificação(4) +
 *   CPF do responsável(11) + CRC-16(4)
 *
 * DIFERENÇA-CHAVE em relação ao tipo "6" (eventos sensíveis, detectados por
 * heartbeat): estes são eventos DECLARADOS conscientemente — disparados nos pontos
 * em que o sistema executa uma inclusão ("I"), alteração ("A") ou exclusão ("E")
 * de cadastro de empregado no REP (ver EmployeeAfdEventRecorderService e os pontos
 * de injeção em EmployeeController/EmployeeControllerActionService/
 * EmployeeChangeRequestController) — mesmo espírito de declaração consciente dos
 * tipos "2" (alteração cadastral da empresa) e "4" (ajuste de relógio).
 *
 * `employee_record_events`: registros imutáveis, NSR canônico (mesma sequência
 * atômica de time_punches/clock_adjustments/company_record_events/
 * rep_availability_events — NsrGeneratorService), protegidos pelos mesmos
 * gatilhos de imutabilidade (bloqueiam UPDATE/DELETE em nível de banco).
 */
class CreateEmployeeRecordEventsTable extends Migration
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
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
                'comment'  => 'NSR canônico — mesma sequência atômica de time_punches.nsr (NsrGeneratorService)',
            ],
            'operation_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 1,
                'null'       => false,
                'comment'    => '"I"=inclusão, "A"=alteração, "E"=exclusão (campo "tipo de operação" do leiaute, tipo 5)',
            ],
            'employee_cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => false,
                'comment'    => 'CPF do empregado envolvido na operação (campo do leiaute, 12 posições — armazenado com formatação para auditoria, formatado/zero-preenchido na geração do AFD)',
            ],
            'employee_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Nome do empregado no momento da operação (campo do leiaute, 52 posições — truncado/preenchido na geração do AFD)',
            ],
            'responsible_cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => false,
                'comment'    => 'CPF do responsável pela operação no REP (campo do leiaute, 11 posições — armazenado com formatação para auditoria)',
            ],
            'recorded_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Data e hora do registro da operação (campo "DH" do leiaute, tipo 5)',
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'SHA-256 do registro — evidência de integridade/imutabilidade, no mesmo espírito de clock_adjustments/company_record_events/rep_availability_events',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nsr');
        $this->forge->addKey('created_at');
        $this->forge->addKey('operation_type');
        $this->forge->addKey('employee_cpf');

        $this->forge->createTable('employee_record_events', true);

        // Imutabilidade: bloqueia UPDATE e DELETE em nível de banco — mesmo padrão
        // aplicado a time_punches, clock_adjustments, company_record_events e
        // rep_availability_events.
        $this->db->query(
            'CREATE OR REPLACE FUNCTION employee_record_events_block_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION \'employee_record_events é imutável: % não é permitido (id=%)\', TG_OP, OLD.id;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;'
        );
        $this->db->query(
            'CREATE TRIGGER trg_employee_record_events_block_update
                BEFORE UPDATE ON employee_record_events
                FOR EACH ROW EXECUTE FUNCTION employee_record_events_block_mutation();'
        );
        $this->db->query(
            'CREATE TRIGGER trg_employee_record_events_block_delete
                BEFORE DELETE ON employee_record_events
                FOR EACH ROW EXECUTE FUNCTION employee_record_events_block_mutation();'
        );
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_employee_record_events_block_update ON employee_record_events;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_employee_record_events_block_delete ON employee_record_events;');
        $this->db->query('DROP FUNCTION IF EXISTS employee_record_events_block_mutation();');
        $this->forge->dropTable('employee_record_events', true);
    }
}

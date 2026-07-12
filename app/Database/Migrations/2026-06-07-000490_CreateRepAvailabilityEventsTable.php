<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabelas de suporte ao registro tipo "6" do AFD — "Eventos sensíveis do REP"
 * (Portaria MTE 671/2021), restrito aos códigos de evento aplicáveis ao REP-P:
 *   "07": disponibilidade de serviço (o sistema voltou a funcionar);
 *   "08": indisponibilidade de serviço (o sistema parou de responder).
 *
 * DIFERENÇA-CHAVE em relação aos tipos "2" e "4" (declarações conscientes de um
 * administrador): o sistema NÃO PODE registrar sua própria indisponibilidade
 * enquanto está fora do ar — por definição, nada roda nesse intervalo. A única
 * forma de detectar o evento é por COMPARAÇÃO entre execuções de um heartbeat
 * periódico (cron, a cada minuto — ver RepHeartbeatCommand/RepAvailabilityMonitorService):
 * se o heartbeat atual encontra um "último sinal de vida" mais antigo do que o
 * esperado, conclui-se que houve uma janela de indisponibilidade entre os dois
 * instantes, e os dois registros (08 no início da janela, 07 no fim) são gravados
 * em conjunto, na mesma transação, consumindo dois NSRs consecutivos da sequência
 * canônica (NsrGeneratorService) — preservando a ordenação global exigida pelo AFD.
 *
 * 1) `rep_heartbeat`: contador de "último sinal de vida" — uma única linha (id=1),
 *    MUTÁVEL (sem gatilhos de imutabilidade), atualizada a cada execução do heartbeat.
 *    É o equivalente, em espírito, ao `nsr_counter`: estado interno de controle, não
 *    um registro AFD em si.
 *
 * 2) `rep_availability_events`: registros imutáveis tipo "6" propriamente ditos —
 *    NSR canônico + código de evento ("07"/"08") + data/hora de gravação (campo "DH"
 *    do leiaute). Mesma proteção via gatilhos de banco aplicada a clock_adjustments
 *    e company_record_events.
 */
class CreateRepAvailabilityEventsTable extends Migration
{
    public function up()
    {
        // ------------------------------------------------------------------
        // rep_heartbeat — estado interno mutável (não é um registro do AFD)
        // ------------------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'       => 'SMALLINT',
                'null'       => false,
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'Timestamp do último heartbeat bem-sucedido — usado para detectar lacunas (indisponibilidade) por comparação entre execuções',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('rep_heartbeat', true);

        $this->db->query("INSERT INTO rep_heartbeat (id, last_seen_at, updated_at) VALUES (1, NULL, NULL) ON CONFLICT (id) DO NOTHING;");

        // ------------------------------------------------------------------
        // rep_availability_events — registros imutáveis tipo "6" (códigos 07/08)
        // ------------------------------------------------------------------
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
            'event_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 2,
                'null'       => false,
                'comment'    => '"07"=disponibilidade de serviço, "08"=indisponibilidade de serviço (únicos códigos aplicáveis ao REP-P)',
            ],
            'recorded_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Data e hora da gravação do registro (campo "DH" do leiaute, tipo 6) — instante estimado do início/fim da janela de indisponibilidade',
            ],
            'detected_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Momento em que o heartbeat detectou e gravou o evento (pode ser posterior ao recorded_at, já que "08" só é percebido quando o serviço volta)',
            ],
            'gap_seconds' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
                'comment'  => 'Duração estimada da janela de indisponibilidade, em segundos — mesma para o par 08/07 gerado na mesma detecção',
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'SHA-256 do registro — evidência de integridade/imutabilidade, no mesmo espírito de clock_adjustments/company_record_events',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nsr');
        $this->forge->addKey('created_at');
        $this->forge->addKey('event_code');

        $this->forge->createTable('rep_availability_events', true);

        // Imutabilidade: bloqueia UPDATE e DELETE em nível de banco — eventos sensíveis
        // compõem o AFD e não podem ser alterados/removidos após registrados, no mesmo
        // espírito de proteção aplicado a time_punches, clock_adjustments e
        // company_record_events.
        $this->db->query(
            'CREATE OR REPLACE FUNCTION rep_availability_events_block_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION \'rep_availability_events é imutável: % não é permitido (id=%)\', TG_OP, OLD.id;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;'
        );
        $this->db->query(
            'CREATE TRIGGER trg_rep_availability_events_block_update
                BEFORE UPDATE ON rep_availability_events
                FOR EACH ROW EXECUTE FUNCTION rep_availability_events_block_mutation();'
        );
        $this->db->query(
            'CREATE TRIGGER trg_rep_availability_events_block_delete
                BEFORE DELETE ON rep_availability_events
                FOR EACH ROW EXECUTE FUNCTION rep_availability_events_block_mutation();'
        );
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_rep_availability_events_block_update ON rep_availability_events;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_rep_availability_events_block_delete ON rep_availability_events;');
        $this->db->query('DROP FUNCTION IF EXISTS rep_availability_events_block_mutation();');
        $this->forge->dropTable('rep_availability_events', true);
        $this->forge->dropTable('rep_heartbeat', true);
    }
}

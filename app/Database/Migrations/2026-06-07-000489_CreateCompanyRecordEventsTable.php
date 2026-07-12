<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabela de declarações de alteração cadastral da empresa no REP-P (SupportPONTO).
 *
 * Alimenta o registro tipo "2" do AFD (Portaria MTE 671/2021 — "Inclusão ou alteração da
 * identificação da empresa no REP"), que exige NSR canônico, data/hora da gravação, CPF do
 * responsável e o "retrato" dos dados cadastrais da empresa registrados no REP naquele momento
 * (tipo de documento/CNPJ-CPF/CNO-CAEPF/razão social/local de prestação de serviços).
 *
 * Assim como o registro tipo "4" (ver migração 2026-06-07-000487_CreateClockAdjustmentsTable),
 * este é um evento RARO e CONSCIENTE — ocorre apenas quando os dados cadastrais da empresa
 * mudam no REP (razão social, CNPJ, endereço/local de prestação de serviços etc.), e não é
 * algo que o sistema deva inferir sozinho. Por isso é DECLARADO por um administrador, que
 * confirma o "antes" (implicitamente, o cadastro vigente até então) e o "depois" (os novos
 * dados, já refletidos em `companyProfile`/`TXTSettingsProvider`).
 *
 * Cada declaração consome um NSR da MESMA sequência canônica usada por time_punches e
 * clock_adjustments — preservando a ordenação global por NSR exigida pelo leiaute do AFD.
 */
class CreateCompanyRecordEventsTable extends Migration
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
                'type'    => 'BIGINT',
                'unsigned' => true,
                'null'    => false,
                'comment' => 'NSR canônico — mesma sequência atômica de time_punches.nsr (NsrGeneratorService)',
            ],
            'recorded_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Data e hora da gravação do registro (campo "DH" do leiaute, tipo 2)',
            ],
            'responsible_cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => false,
                'comment'    => 'CPF do responsável pela inclusão/alteração cadastral (obrigatório no registro tipo 2)',
            ],
            'employer_doc_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 1,
                'null'       => false,
                'default'    => '1',
                'comment'    => '"1"=CNPJ, "2"=CPF — tipo de documento do empregador no momento da declaração',
            ],
            'employer_doc' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => false,
                'comment'    => 'CNPJ ou CPF do empregador (retrato no momento da declaração)',
            ],
            'cno_caepf' => [
                'type'       => 'VARCHAR',
                'constraint' => 14,
                'null'       => true,
                'comment'    => 'CNO ou CAEPF, quando existir (retrato no momento da declaração)',
            ],
            'company_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
                'comment'    => 'Razão social/nome do empregador (retrato no momento da declaração)',
            ],
            'service_location' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Local de prestação de serviços (retrato no momento da declaração)',
            ],
            'reason' => [
                'type'    => 'TEXT',
                'null'    => false,
                'comment' => 'Justificativa da alteração cadastral (ex.: mudança de endereço, alteração de razão social)',
            ],
            'declared_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
                'comment'  => 'employees.id do administrador que registrou a declaração (ver NOTA da migração 2026-06-07-000488 sobre por que a FK aponta para employees, e não users)',
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'SHA-256 do registro — evidência de integridade/imutabilidade, no mesmo espírito de clock_adjustments',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nsr');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('declared_by', 'employees', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('company_record_events', true);

        // Imutabilidade: bloqueia UPDATE e DELETE em nível de banco — declarações cadastrais
        // compõem o AFD e não podem ser alteradas/removidas após registradas, no mesmo
        // espírito de proteção aplicado a time_punches, audit_logs e clock_adjustments.
        $this->db->query(
            'CREATE OR REPLACE FUNCTION company_record_events_block_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION \'company_record_events é imutável: % não é permitido (id=%)\', TG_OP, OLD.id;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;'
        );
        $this->db->query(
            'CREATE TRIGGER trg_company_record_events_block_update
                BEFORE UPDATE ON company_record_events
                FOR EACH ROW EXECUTE FUNCTION company_record_events_block_mutation();'
        );
        $this->db->query(
            'CREATE TRIGGER trg_company_record_events_block_delete
                BEFORE DELETE ON company_record_events
                FOR EACH ROW EXECUTE FUNCTION company_record_events_block_mutation();'
        );
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_company_record_events_block_update ON company_record_events;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_company_record_events_block_delete ON company_record_events;');
        $this->db->query('DROP FUNCTION IF EXISTS company_record_events_block_mutation();');
        $this->forge->dropTable('company_record_events', true);
    }
}

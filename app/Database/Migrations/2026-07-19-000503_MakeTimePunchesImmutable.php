<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Trigger de imutabilidade para time_punches, no mesmo espírito de
 * audit_logs (2026-03-26-000003) e clock_adjustments (2026-06-07-000487).
 *
 * Diferente das duas outras tabelas, time_punches tem uma mutação legítima
 * e ativa em produção: TimesheetWorkflowService::approve()/reject() faz
 * UPDATE em status/approved_by/approved_at/rejected_by/rejected_at/
 * rejection_reason/notes -- um bloqueio total de UPDATE (como em
 * clock_adjustments) quebraria esse fluxo. Por isso o trigger é
 * column-scoped: bloqueia alteração dos campos evidenciais da marcação
 * (nsr, punch_time, hash, hash chain, geolocalização, etc.) e permite
 * apenas os campos do workflow de aprovação/rejeição. DELETE é sempre
 * bloqueado -- nenhum código da aplicação apaga linhas de time_punches.
 */
class MakeTimePunchesImmutable extends Migration
{
    private string $table = 'time_punches';

    /**
     * Campos que TimesheetWorkflowService legitimamente atualiza.
     * Qualquer alteração fora desta lista é bloqueada pelo trigger.
     */
    private array $mutableColumns = [
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
    ];

    public function up(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        $existingColumns = $db->getFieldNames($this->table);
        $protectedColumns = array_values(array_diff($existingColumns, $this->mutableColumns, ['id']));

        $checks = [];
        foreach ($protectedColumns as $column) {
            $checks[] = "OLD.\"{$column}\" IS DISTINCT FROM NEW.\"{$column}\"";
        }
        $condition = implode(' OR ', $checks);

        $db->query("
            CREATE OR REPLACE FUNCTION fn_time_punches_block_mutation() RETURNS TRIGGER AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION
                        'time_punches é imutável: DELETE não é permitido (id=%)', OLD.id;
                    RETURN NULL;
                END IF;

                IF {$condition} THEN
                    RAISE EXCEPTION
                        'time_punches é imutável: apenas campos do fluxo de aprovação '
                        '(status/approved_*/rejected_*/notes) podem ser alterados (id=%)', OLD.id;
                    RETURN NULL;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        $db->query("DROP TRIGGER IF EXISTS trg_time_punches_block_update ON {$this->table};");
        $db->query("DROP TRIGGER IF EXISTS trg_time_punches_block_delete ON {$this->table};");

        $db->query("
            CREATE TRIGGER trg_time_punches_block_update
            BEFORE UPDATE ON {$this->table}
            FOR EACH ROW EXECUTE FUNCTION fn_time_punches_block_mutation();
        ");

        $db->query("
            CREATE TRIGGER trg_time_punches_block_delete
            BEFORE DELETE ON {$this->table}
            FOR EACH ROW EXECUTE FUNCTION fn_time_punches_block_mutation();
        ");
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        $db->query("DROP TRIGGER IF EXISTS trg_time_punches_block_update ON {$this->table}");
        $db->query("DROP TRIGGER IF EXISTS trg_time_punches_block_delete ON {$this->table}");
        $db->query('DROP FUNCTION IF EXISTS fn_time_punches_block_mutation()');
    }
}

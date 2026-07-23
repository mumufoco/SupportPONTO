<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Ferramenta operacional para corrigir com segurança um drift do contador NSR
 * (nsr_counter atrás do maior NSR já persistido) sem exigir SQL manual.
 *
 * Nunca move o contador para trás — apenas adianta para MAX(nsr) entre as 5
 * tabelas que compartilham a sequência canônica, e nunca edita/apaga os
 * registros imutáveis já gravados (mesmo quando há uma duplicata histórica
 * entre eles, o que é apenas reportado, nunca "corrigido" às cegas).
 */
class NsrResyncCounter extends BaseCommand
{
    protected $group       = 'Timesheet';
    protected $name        = 'nsr:resync-counter';
    protected $description = 'Corrige o contador NSR (nsr_counter) para MAX(nsr) entre as tabelas canônicas e preenche o nsr_ledger retroativamente.';

    private const TABLES = [
        'time_punches'            => 'created_at',
        'clock_adjustments'       => 'adjusted_datetime',
        'rep_availability_events' => 'recorded_at',
        'employee_record_events'  => 'recorded_at',
        'company_record_events'   => 'recorded_at',
    ];

    public function run(array $params)
    {
        $db = Database::connect();

        if (! $db->tableExists('nsr_counter')) {
            CLI::error('Tabela nsr_counter não existe. Rode as migrations antes.');
            return;
        }

        $maxNsr = 0;
        foreach (self::TABLES as $table => $timestampColumn) {
            if (! $db->tableExists($table) || ! $db->fieldExists('nsr', $table)) {
                continue;
            }

            $row = $db->table($table)->selectMax('nsr', 'max_nsr')->get()->getRow();
            $tableMax = ($row && $row->max_nsr) ? (int) $row->max_nsr : 0;
            $maxNsr = max($maxNsr, $tableMax);
        }

        $counterRow = $db->table('nsr_counter')->select('value')->where('id', 1)->get()->getRowArray();
        $currentValue = isset($counterRow['value']) ? (int) $counterRow['value'] : null;

        CLI::write("Maior NSR persistido nas tabelas canônicas: {$maxNsr}", 'cyan');
        CLI::write('Valor atual de nsr_counter: ' . ($currentValue === null ? 'AUSENTE' : $currentValue), 'cyan');

        if ($currentValue === null) {
            $db->table('nsr_counter')->insert(['id' => 1, 'value' => $maxNsr, 'updated_at' => date('Y-m-d H:i:s')]);
            CLI::write("nsr_counter não tinha registro inicial — criado com value={$maxNsr}.", 'green');
        } elseif ($currentValue < $maxNsr) {
            $db->table('nsr_counter')->where('id', 1)->update(['value' => $maxNsr, 'updated_at' => date('Y-m-d H:i:s')]);
            CLI::write("nsr_counter estava atrás ({$currentValue} < {$maxNsr}) — corrigido para {$maxNsr}.", 'green');
        } else {
            CLI::write('nsr_counter já está coerente (nada a fazer). Nunca movo o contador para trás.', 'green');
        }

        if ($db->tableExists('nsr_ledger')) {
            $inserted = 0;
            foreach (self::TABLES as $table => $timestampColumn) {
                if (! $db->tableExists($table) || ! $db->fieldExists('nsr', $table)) {
                    continue;
                }

                $result = $db->query(
                    "INSERT INTO nsr_ledger (nsr, source, issued_at)
                     SELECT nsr, ?, {$timestampColumn} FROM {$table} WHERE nsr IS NOT NULL
                     ON CONFLICT (nsr) DO NOTHING",
                    [$table]
                );
                $inserted += $db->affectedRows();
            }
            CLI::write("nsr_ledger: {$inserted} NSR(s) retroativo(s) preenchido(s) (duplicatas pré-existentes são ignoradas, não sobrescritas).", 'cyan');
        } else {
            CLI::write('nsr_ledger não existe ainda — rode a migration CreateNsrLedgerTable.', 'yellow');
        }

        $unions = [];
        foreach (self::TABLES as $table => $timestampColumn) {
            if (! $db->tableExists($table) || ! $db->fieldExists('nsr', $table)) {
                continue;
            }
            $unions[] = "SELECT CAST(nsr AS BIGINT) AS nsr, '{$table}' AS source_table FROM {$table} WHERE nsr IS NOT NULL";
        }

        if ($unions !== []) {
            $sql = 'SELECT nsr, COUNT(*) AS total, STRING_AGG(DISTINCT source_table, \', \' ORDER BY source_table) AS tables
                     FROM (' . implode(' UNION ALL ', $unions) . ') all_nsr
                     GROUP BY nsr HAVING COUNT(*) > 1 ORDER BY nsr';
            $duplicates = $db->query($sql)->getResultArray();

            if ($duplicates !== []) {
                CLI::write('');
                CLI::write('ATENÇÃO — duplicatas de NSR encontradas entre tabelas (não podem ser corrigidas retroativamente; registros são imutáveis):', 'yellow');
                foreach ($duplicates as $dup) {
                    CLI::write("  NSR {$dup['nsr']}: {$dup['total']}x em [{$dup['tables']}]", 'yellow');
                }
            } else {
                CLI::write('Nenhuma duplicata de NSR entre tabelas.', 'green');
            }
        }
    }
}

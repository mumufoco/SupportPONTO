<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Support\SchemaAuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportSchemaAudit extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:schema-audit';
    protected $description = 'Audita a cobertura do schema: tabelas esperadas, tabelas existentes, colunas mínimas e tabela de migrations.';
    protected $usage = 'support:schema-audit [--json] [--no-connections] [--save]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--no-connections' => 'Não consulta o banco; apenas calcula a cobertura esperada do schema.',
        '--save' => 'Salva o relatório em build/support/schema-audit.json.',
    ];

    public function run(array $params)
    {
        $service = new SchemaAuditService();
        $report = $service->build(! CLI::getOption('no-connections'));

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/support';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            file_put_contents($dir . '/schema-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            CLI::write('Relatório salvo em: build/support/schema-audit.json', 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        $status = (string) ($report['status'] ?? 'unknown');
        CLI::write('========================================', 'blue');
        CLI::write('  SupportPONTO - Auditoria de Schema', 'blue');
        CLI::write('========================================', 'blue');
        CLI::newLine();
        CLI::write('Status: ' . strtoupper($status), match ($status) {
            'ok' => 'green',
            'warning' => 'yellow',
            default => 'red',
        });
        CLI::write('Tabelas esperadas: ' . (string) ($report['summary']['expected_tables'] ?? 0));
        CLI::write('Tabelas ausentes: ' . (string) ($report['summary']['missing_tables'] ?? 0));
        CLI::write('Tabelas críticas ausentes: ' . (string) ($report['summary']['missing_critical_tables'] ?? 0));
        CLI::write('Problemas de colunas mínimas: ' . (string) ($report['summary']['column_issues'] ?? 0));
        CLI::write('Banco: ' . (string) ($report['database']['details'] ?? 'n/d'));

        $missingCritical = $report['database']['missing_critical_tables'] ?? [];
        if (is_array($missingCritical) && $missingCritical !== []) {
            CLI::newLine();
            CLI::write('Tabelas críticas ausentes:', 'red');
            foreach ($missingCritical as $table) {
                CLI::write('- ' . $table, 'red');
            }
        }

        $columnIssues = $report['database']['column_issues'] ?? [];
        if (is_array($columnIssues) && $columnIssues !== []) {
            CLI::newLine();
            CLI::write('Problemas de colunas mínimas:', 'yellow');
            foreach ($columnIssues as $table => $columns) {
                CLI::write('- ' . $table . ': ' . implode(', ', (array) $columns), 'yellow');
            }
        }
    }
}

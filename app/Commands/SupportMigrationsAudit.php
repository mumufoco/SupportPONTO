<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Support\MigrationsAuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportMigrationsAudit extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:migrations-audit';
    protected $description = 'Audita discovery, nomenclatura e estado das migrations do SupportPONTO.';
    protected $usage = 'support:migrations-audit [--json] [--no-connections] [--save]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--no-connections' => 'Não consulta o banco nem a tabela de controle de migrations.',
        '--save' => 'Salva o relatório em build/support/migrations-audit.json.',
    ];

    public function run(array $params)
    {
        $service = new MigrationsAuditService();
        $report = $service->build(! CLI::getOption('no-connections'));

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/support';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            file_put_contents($dir . '/migrations-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            CLI::write('Relatório salvo em: build/support/migrations-audit.json', 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        $status = (string) ($report['status'] ?? 'unknown');
        CLI::write('===========================================', 'blue');
        CLI::write('  SupportPONTO - Auditoria de Migrations', 'blue');
        CLI::write('===========================================', 'blue');
        CLI::newLine();
        CLI::write('Status: ' . strtoupper($status), match ($status) {
            'ok' => 'green',
            'warning' => 'yellow',
            default => 'red',
        });
        CLI::write('Arquivos encontrados: ' . (string) ($report['summary']['migration_files'] ?? 0));
        CLI::write('Arquivos inválidos: ' . (string) ($report['summary']['invalid_filenames'] ?? 0));
        CLI::write('Prefixos duplicados: ' . (string) ($report['summary']['duplicate_prefixes'] ?? 0));
        CLI::write('Banco: ' . (string) ($report['database']['details'] ?? 'n/d'));

        $invalid = $report['naming']['invalid_files'] ?? [];
        if (is_array($invalid) && $invalid !== []) {
            CLI::newLine();
            CLI::write('Arquivos fora do padrão:', 'yellow');
            foreach ($invalid as $file) {
                CLI::write('- ' . $file, 'yellow');
            }
        }

        $duplicates = $report['naming']['duplicate_prefixes'] ?? [];
        if (is_array($duplicates) && $duplicates !== []) {
            CLI::newLine();
            CLI::write('Prefixos duplicados:', 'red');
            foreach ($duplicates as $prefix => $files) {
                CLI::write('- ' . $prefix . ': ' . implode(', ', (array) $files), 'red');
            }
        }
    }
}

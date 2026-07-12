<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Installer\InstallerDependencyAutomationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportInstallerDependenciesInstall extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:installer-deps';
    protected $description = 'Executa ou simula a instalação automática das dependências da aplicação no novo instalador.';
    protected $usage = 'support:installer-deps [--plan] [--dry-run] [--json]';
    protected $options = [
        '--plan' => 'Exibe apenas o plano de automação.',
        '--dry-run' => 'Executa em modo de simulação e salva o relatório.',
        '--json' => 'Emite saída em JSON.',
    ];

    public function run(array $params)
    {
        $service = new InstallerDependencyAutomationService();

        if (CLI::getOption('plan')) {
            $report = $service->plan();
        } else {
            $report = $service->execute((bool) CLI::getOption('dry-run'));
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('==============================================', 'blue');
        CLI::write(' SupportPONTO - Dependências da Aplicação ', 'blue');
        CLI::write('==============================================', 'blue');
        CLI::newLine();
        CLI::write('Status geral: ' . strtoupper((string) ($report['status'] ?? 'unknown')), ($report['status'] ?? '') === 'ok' ? 'green' : (($report['status'] ?? '') === 'warning' ? 'yellow' : 'red'));
        CLI::newLine();

        $steps = $report['steps'] ?? $report['results'] ?? [];
        foreach ($steps as $step) {
            $name = (string) ($step['label'] ?? $step['name'] ?? 'Etapa');
            $severity = strtoupper((string) ($step['severity'] ?? 'INFO'));
            CLI::write('- [' . $severity . '] ' . $name);
            CLI::write('  ' . (string) ($step['details'] ?? ''));
            if (! empty($step['command'])) {
                CLI::write('  Comando: ' . (string) $step['command']);
            }
            if (! empty($step['manual_command'])) {
                CLI::write('  Manual: ' . (string) $step['manual_command']);
            }
            if (array_key_exists('result', $step)) {
                CLI::write('  Resultado: ' . (string) $step['result']);
            }
            if (! empty($step['stdout_log'])) {
                CLI::write('  STDOUT: ' . (string) $step['stdout_log']);
            }
            if (! empty($step['stderr_log'])) {
                CLI::write('  STDERR: ' . (string) $step['stderr_log']);
            }
        }
    }
}

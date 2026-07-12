<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Support\SupportDiagnosticsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportDiagnostics extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:diagnostics';
    protected $description = 'Gera um diagnóstico operacional consolidado para suporte e produção.';
    protected $usage = 'support:diagnostics [options]';
    protected $options = [
        '--json' => 'Exibe o diagnóstico em JSON.',
        '--no-connections' => 'Não testa conexões reais com banco/Redis/DeepFace.',
        '--save' => 'Salva o relatório em build/support/support-diagnostics.json.',
    ];

    public function run(array $params)
    {
        $service = new SupportDiagnosticsService();
        $withConnections = ! CLI::getOption('no-connections');
        $report = $service->build($withConnections);

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/support';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $file = $dir . '/support-diagnostics.json';
            file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            CLI::write('Relatório salvo em: ' . $file, 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('===========================================', 'blue');
        CLI::write('  SupportPONTO - Diagnóstico Operacional', 'blue');
        CLI::write('===========================================', 'blue');
        CLI::newLine();
        CLI::write('Status geral: ' . strtoupper((string) ($report['status'] ?? 'unknown')), ($report['status'] ?? 'critical') === 'ok' ? 'green' : 'yellow');
        CLI::write('Release: ' . (($report['release']['release'] ?? 'unknown') . ' / pacote ' . ($report['release']['package'] ?? 'n/d')), 'white');
        CLI::write('Gerado em: ' . ($report['generated_at'] ?? 'n/d'), 'white');
        CLI::newLine();

        $summary = $report['health']['summary'] ?? [];
        CLI::write('Alertas operacionais: ' . ($summary['alerts_count'] ?? 0), ((int) ($summary['alerts_count'] ?? 0)) > 0 ? 'yellow' : 'green');
        CLI::write('Módulos críticos: ' . ($summary['critical_modules'] ?? 0), ((int) ($summary['critical_modules'] ?? 0)) > 0 ? 'red' : 'green');
        CLI::write('Módulos degradados: ' . ($summary['degraded_modules'] ?? 0), ((int) ($summary['degraded_modules'] ?? 0)) > 0 ? 'yellow' : 'green');
        CLI::newLine();

        foreach (($report['installation']['checks'] ?? []) as $check) {
            $severity = (string) ($check['severity'] ?? 'warning');
            $color = match ($severity) {
                'ok' => 'green',
                'warning' => 'yellow',
                default => 'red',
            };
            CLI::write(sprintf('[%s] %s — %s', strtoupper($severity), $check['label'] ?? 'check', $check['details'] ?? ''), $color);
        }
    }
}

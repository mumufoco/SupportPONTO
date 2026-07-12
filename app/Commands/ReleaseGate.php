<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Release\ReleaseGateService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ReleaseGate extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'release:gate';
    protected $description = 'Executa o gate automatizado final de liberação para produção.';
    protected $usage = 'release:gate [options]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--no-connections' => 'Não testa conexões reais de banco/Redis/DeepFace.',
        '--save' => 'Salva os artefatos em build/release/.',
    ];

    public function run(array $params)
    {
        $service = new ReleaseGateService();
        $withConnections = ! CLI::getOption('no-connections');
        $report = $service->build($withConnections);

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/release';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/release-gate-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            file_put_contents($dir . '/release-gate-report.md', $service->toMarkdown($report));
            CLI::write('Artefatos salvos em build/release/', 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('===========================================', 'blue');
        CLI::write('  SupportPONTO - QA Release Gate', 'blue');
        CLI::write('===========================================', 'blue');
        CLI::newLine();
        CLI::write('Status: ' . strtoupper((string) ($report['status'] ?? 'unknown')), match ((string) ($report['status'] ?? 'unknown')) {
            'approved' => 'green',
            'approved_with_reservations' => 'yellow',
            default => 'red',
        });
        CLI::write('Release: ' . (($report['release']['release'] ?? 'unknown') . ' / pacote ' . ($report['release']['package'] ?? 'n/d')));
        CLI::write('Decisão: ' . (string) ($report['decision']['message'] ?? 'n/d'));
        CLI::newLine();

        foreach (($report['checks'] ?? []) as $check) {
            $status = (string) ($check['status'] ?? 'unknown');
            $color = match ($status) {
                'approved' => 'green',
                'reservations' => 'yellow',
                default => 'red',
            };
            CLI::write(sprintf('[%s] %s — %s', strtoupper($status), (string) ($check['label'] ?? 'gate'), (string) ($check['details'] ?? '')), $color);
        }
    }
}

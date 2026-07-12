<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Support\ConfigurationAuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportConfigAudit extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:config-audit';
    protected $description = 'Audita cache, proxy/HTTPS, sessão e runtime CLI para diagnóstico operacional.';
    protected $usage = 'support:config-audit [--json] [--save]';
    protected $options = [
        '--json' => 'Exibe o relatório em JSON.',
        '--save' => 'Salva o relatório em build/support/config-audit.json.',
    ];

    public function run(array $params): void
    {
        $service = new ConfigurationAuditService();
        $report = $service->build();

        $asJson = CLI::getOption('json') !== null;
        $save = CLI::getOption('save') !== null;

        if ($save) {
            $dir = ROOTPATH . 'build/support';
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/config-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if ($asJson) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('Status: ' . strtoupper((string) ($report['status'] ?? 'unknown')));
        foreach (($report['checks'] ?? []) as $check) {
            CLI::write(sprintf('[%s] %s => %s', strtoupper((string) $check['severity']), (string) $check['label'], (string) $check['details']));
        }
    }
}

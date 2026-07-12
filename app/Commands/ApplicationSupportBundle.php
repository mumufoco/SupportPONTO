<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Observability\OperationalTelemetryService;
use App\Services\Support\ApplicationSupportBundleService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ApplicationSupportBundle extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:bundle';
    protected $description = 'Gera um bundle sanitizado de suporte operacional da aplicação.';
    protected $usage = 'support:bundle [--json] [--cleanup-observability]';
    protected $options = [
        '--json' => 'Exibe o resultado em JSON.',
        '--cleanup-observability' => 'Remove eventos operacionais antigos conforme retenção.',
    ];

    public function run(array $params)
    {
        if (CLI::getOption('cleanup-observability')) {
            $deleted = (new OperationalTelemetryService())->cleanupOldEvents();
            $payload = ['success' => true, 'deleted_files' => $deleted];
            if (CLI::getOption('json')) {
                CLI::write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }
            CLI::write('Eventos antigos removidos: ' . $deleted, 'green');
            return;
        }

        $result = (new ApplicationSupportBundleService())->build();

        if (CLI::getOption('json')) {
            CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('SupportPONTO — Bundle de suporte da aplicação', 'blue');
        CLI::write('Bundle: ' . ($result['bundle_path'] ?? 'não gerado'), ($result['success'] ?? false) ? 'green' : 'yellow');
        CLI::write('Diretório de trabalho: ' . ($result['work_directory'] ?? 'n/d'), 'white');
        CLI::write('Arquivos: ' . count($result['files'] ?? []), 'white');
    }
}

<?php

namespace App\Commands;

use App\Services\Queue\TemporaryFileCleanupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanupAsyncJobFiles extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'jobs:cleanup';
    protected $description = 'Remove arquivos temporários e resultados antigos gerados por jobs.';
    protected $usage = 'jobs:cleanup [--dry-run] [--temp-ttl-hours 24] [--result-ttl-hours 72]';
    protected $options = [
        '--dry-run' => 'Simula a limpeza sem excluir arquivos.',
        '--temp-ttl-hours' => 'TTL em horas para temporários genéricos.',
        '--result-ttl-hours' => 'TTL em horas para resultados de relatórios/jobs.',
    ];

    public function run(array $params)
    {
        $dryRun = CLI::getOption('dry-run') !== null;
        $tempTtl = CLI::getOption('temp-ttl-hours');
        $resultTtl = CLI::getOption('result-ttl-hours');

        $service = new TemporaryFileCleanupService();
        $result = $service->cleanup(
            $dryRun,
            is_numeric($tempTtl) ? (int) $tempTtl : null,
            is_numeric($resultTtl) ? (int) $resultTtl : null
        );

        CLI::write('Limpeza de arquivos de jobs', 'green');
        CLI::write('Dry-run: ' . ($result['dry_run'] ? 'sim' : 'não'));
        CLI::write('Arquivos varridos: ' . $result['scanned']);
        CLI::write('Arquivos removíveis/removidos: ' . $result['deleted']);
        CLI::write('Bytes liberáveis/liberados: ' . $result['freed_bytes']);

        foreach ($result['errors'] as $error) {
            CLI::write(' - ' . $error, 'yellow');
        }
    }
}

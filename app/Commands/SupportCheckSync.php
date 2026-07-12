<?php

namespace App\Commands;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Services\SupportCheck\SupportCheckSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportCheckSync extends BaseCommand
{
    protected $group       = 'SupportCheck';
    protected $name        = 'supportcheck:sync';
    protected $description = 'Sincroniza estrutura organizacional e funcionarios com o SupportCHECK.';
    protected $usage       = 'supportcheck:sync [--structure] [--employees] [--all]';
    protected $options     = [
        '--structure'  => 'Sincroniza empresa, cargos, departamentos e unidades.',
        '--employees'  => 'Sincroniza todos os funcionarios ativos.',
        '--all'        => 'Sincroniza estrutura e funcionarios (equivale a --structure --employees).',
    ];

    public function run(array $params): void
    {
        $client = new SupportCheckApiClient();

        if (! $client->isEnabled()) {
            CLI::write('SupportCHECK nao esta habilitado. Verifique SUPPORTCHECK_ENABLED, SUPPORTCHECK_BASE_URL e SUPPORTCHECK_API_TOKEN no .env.', 'yellow');
            return;
        }

        $all        = CLI::getOption('all') !== null;
        $structure  = $all || CLI::getOption('structure') !== null;
        $employees  = $all || CLI::getOption('employees') !== null;

        if (! $structure && ! $employees) {
            CLI::write('Especifique --structure, --employees ou --all.', 'red');
            $this->showHelp();
            return;
        }

        $service = new SupportCheckSyncService($client);

        if ($structure) {
            CLI::write('Sincronizando estrutura organizacional...', 'cyan');
            try {
                $result = $service->syncStructure();
                CLI::write('Empresa:        OK', 'green');
                CLI::write('Cargos:         OK (' . count($result['positions']['data']['items'] ?? []) . ' itens)', 'green');
                CLI::write('Departamentos:  OK (' . count($result['departments']['data']['items'] ?? []) . ' itens)', 'green');
                CLI::write('Unidades:       OK (' . count($result['work_units']['data']['items'] ?? []) . ' itens)', 'green');
            } catch (\Throwable $e) {
                CLI::write('Erro ao sincronizar estrutura: ' . $e->getMessage(), 'red');
            }
        }

        if ($employees) {
            CLI::write('Sincronizando funcionarios...', 'cyan');
            try {
                $result = $service->syncAllEmployees();
                CLI::write('Enviados com sucesso: ' . $result['synced'], 'green');
                if ($result['failed'] > 0) {
                    CLI::write('Falhas: ' . $result['failed'], 'red');
                    foreach ($result['errors'] as $err) {
                        CLI::write('  - ' . $err, 'yellow');
                    }
                }
            } catch (\Throwable $e) {
                CLI::write('Erro ao sincronizar funcionarios: ' . $e->getMessage(), 'red');
            }
        }

        CLI::write('Concluido.', 'green');
    }
}

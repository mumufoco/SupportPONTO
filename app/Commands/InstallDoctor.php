<?php

namespace App\Commands;

use App\Services\Installer\InstallationDoctorService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InstallDoctor extends BaseCommand
{
    protected $group = 'Install';
    protected $name = 'install:doctor';
    protected $description = 'Executa diagnóstico de pré-instalação/pós-instalação do ambiente SupportPONTO.';
    protected $usage = 'install:doctor [--json] [--no-connections]';
    protected $options = [
        '--json' => 'Exibe o resultado em JSON.',
        '--no-connections' => 'Não testa conexões ativas com banco, Redis e DeepFace.',
    ];

    public function run(array $params)
    {
        $json = CLI::getOption('json') !== null;
        $withConnections = CLI::getOption('no-connections') === null;

        $service = new InstallationDoctorService();
        $report = $service->inspect($withConnections);

        if ($json) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('SupportPONTO Installation Doctor', 'yellow');
        CLI::write('Status geral: ' . strtoupper($report['status']), $report['status'] === 'ok' ? 'green' : ($report['status'] === 'warning' ? 'yellow' : 'red'));
        CLI::newLine();

        foreach ($report['checks'] as $check) {
            $color = match ($check['severity']) {
                'ok' => 'green',
                'warning' => 'yellow',
                default => 'red',
            };
            CLI::write('[' . strtoupper($check['severity']) . '] ' . $check['label'] . ' => ' . $check['value'], $color);
            CLI::write('    ' . $check['details']);
        }

        CLI::newLine();
        if ($report['status'] === 'failed') {
            CLI::error('Há bloqueadores que impedem instalação/produção segura.');
        } elseif ($report['status'] === 'warning') {
            CLI::write('Há avisos que devem ser revisados antes da produção.', 'yellow');
        } else {
            CLI::write('Ambiente apto nos checks executados.', 'green');
        }
    }
}

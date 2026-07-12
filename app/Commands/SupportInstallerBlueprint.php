<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Installer\AutomaticInstallerArchitectureService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportInstallerBlueprint extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:installer-blueprint';
    protected $description = 'Exibe a arquitetura do novo instalador automático estilo WordPress do SupportPONTO.';
    protected $usage = 'support:installer-blueprint [--json] [--save]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--save' => 'Salva o blueprint em build/support/installer-blueprint.json.',
    ];

    public function run(array $params)
    {
        $report = (new AutomaticInstallerArchitectureService())->blueprint();

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/support';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/installer-blueprint.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            CLI::write('Blueprint salvo em: build/support/installer-blueprint.json', 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('========================================', 'blue');
        CLI::write('  SupportPONTO - Blueprint do Instalador', 'blue');
        CLI::write('========================================', 'blue');
        CLI::newLine();
        CLI::write('Status: ' . strtoupper((string) ($report['status'] ?? 'unknown')), (string) ($report['status'] ?? 'unknown') === 'ok' ? 'green' : 'yellow');
        CLI::write('Entrypoint: ' . (string) ($report['contract']['entrypoint'] ?? '/install.php'));
        CLI::write('Contrato canônico: ' . (string) ($report['contract']['canonical_mode'] ?? 'single-entrypoint'));
        CLI::newLine();
        CLI::write('Etapas planejadas:', 'blue');
        foreach (($report['steps'] ?? []) as $step) {
            CLI::write('- ' . $step['key'] . ': ' . $step['label'] . ' — ' . $step['goal']);
        }
        CLI::newLine();
        CLI::write('Automação:', 'blue');
        CLI::write('- Composer: ' . (((bool) ($report['automation']['composer']['enabled'] ?? false)) ? 'habilitado' : 'desabilitado'));
        CLI::write('- Node/NPM: ' . (((bool) ($report['automation']['node']['enabled'] ?? false)) ? 'habilitado' : 'desabilitado'));
        CLI::write('- Writable bootstrap: ' . (((bool) ($report['automation']['writable_bootstrap']['enabled'] ?? false)) ? 'habilitado' : 'desabilitado'));
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Installer\InstallerPrecheckService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportInstallerPrecheck extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:installer-precheck';
    protected $description = 'Executa o pré-check completo do novo instalador automático do SupportPONTO.';
    protected $usage = 'support:installer-precheck [--json] [--save] [--no-connections]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--save' => 'Salva o relatório em build/support/installer-precheck.json.',
        '--no-connections' => 'Não testa conexões externas (banco/redis/deepface).',
    ];

    public function run(array $params)
    {
        $withConnections = !CLI::getOption('no-connections');
        $report = (new InstallerPrecheckService())->build($withConnections);

        if (CLI::getOption('save')) {
            $dir = ROOTPATH . 'build/support';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/installer-precheck.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            CLI::write('Relatório salvo em: build/support/installer-precheck.json', 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        CLI::write('========================================', 'blue');
        CLI::write(' SupportPONTO - Pré-check do Instalador ', 'blue');
        CLI::write('========================================', 'blue');
        CLI::newLine();
        $status = (string) ($report['status'] ?? 'unknown');
        CLI::write('Status geral: ' . strtoupper($status), $status === 'ok' ? 'green' : ($status === 'warning' ? 'yellow' : 'red'));
        CLI::newLine();
        CLI::write('Seções:', 'blue');
        foreach (($report['summary']['sections'] ?? []) as $section => $sectionStatus) {
            CLI::write('- ' . $section . ': ' . strtoupper((string) $sectionStatus));
        }
        CLI::newLine();
        CLI::write('Próximas ações:', 'blue');
        foreach (($report['next_actions'] ?? []) as $action) {
            $line = '- [' . strtoupper((string) ($action['severity'] ?? 'info')) . '] ' . (string) ($action['title'] ?? 'Ação');
            CLI::write($line);
            CLI::write('  ' . (string) ($action['details'] ?? ''));
            if (!empty($action['command'])) {
                CLI::write('  Comando sugerido: ' . $action['command']);
            }
        }
    }
}

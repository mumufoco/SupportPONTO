<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Installer\InstallerFinalizationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportInstallerFinalizationAudit extends BaseCommand
{
    protected $group = 'Support';
    protected $name = 'support:installer-finalization';
    protected $description = 'Audita o fechamento final e a homologação do novo instalador automático.';
    protected $usage = 'support:installer-finalization [--json] [--save]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--save' => 'Salva o relatório em build/support/installer-finalization.json.',
    ];

    public function run(array $params)
    {
        $service = new InstallerFinalizationService();
        $report = $service->inspect();

        if (CLI::getOption('save')) {
            $path = $service->save($report);
            CLI::write('Relatório salvo em: ' . str_replace(ROOTPATH, '', $path), 'green');
        }

        if (CLI::getOption('json')) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        $status = (string) ($report['status'] ?? 'unknown');
        CLI::write('============================================', 'blue');
        CLI::write(' SupportPONTO - Homologação Final Installer ', 'blue');
        CLI::write('============================================', 'blue');
        CLI::newLine();
        CLI::write('Status geral: ' . strtoupper($status), $status === 'ok' ? 'green' : ($status === 'warning' ? 'yellow' : 'red'));
        CLI::newLine();

        foreach (($report['checks'] ?? []) as $check) {
            $label = (string) ($check['label'] ?? 'Check');
            $detail = (string) ($check['detail'] ?? '');
            $checkStatus = (string) ($check['status'] ?? 'unknown');
            CLI::write('- [' . strtoupper($checkStatus) . '] ' . $label . ($detail !== '' ? ' => ' . $detail : ''));
        }

        if (! empty($report['next_actions'])) {
            CLI::newLine();
            CLI::write('Próximas ações:', 'blue');
            foreach ($report['next_actions'] as $action) {
                CLI::write('- ' . (string) ($action['title'] ?? 'Ação'));
                CLI::write('  ' . (string) ($action['details'] ?? ''));
                if (! empty($action['command'])) {
                    CLI::write('  Comando sugerido: ' . $action['command']);
                }
            }
        }
    }
}

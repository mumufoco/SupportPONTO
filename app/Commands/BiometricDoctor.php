<?php

namespace App\Commands;

use App\Services\Biometric\BiometricProductionReadinessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BiometricDoctor extends BaseCommand
{
    protected $group = 'SupportPONTO';
    protected $name = 'biometric:doctor';
    protected $description = 'Executa diagnóstico operacional da biometria facial e DeepFace.';
    protected $usage = 'biometric:doctor [--json] [--no-connections] [--cleanup-orphans] [--dry-run]';
    protected $options = [
        '--json' => 'Exibe a saída em JSON.',
        '--no-connections' => 'Não testa a conectividade ativa com a API DeepFace.',
        '--cleanup-orphans' => 'Varre e remove arquivos faciais órfãos.',
        '--dry-run' => 'Executa apenas simulação do cleanup de órfãos.',
    ];

    public function run(array $params)
    {
        $service = new BiometricProductionReadinessService();
        $withConnections = ! CLI::getOption('no-connections');
        $json = (bool) CLI::getOption('json');
        $cleanup = (bool) CLI::getOption('cleanup-orphans');
        $dryRun = CLI::getOption('dry-run') !== false || ! $cleanup;

        $result = $service->diagnostics($withConnections);
        if ($cleanup) {
            $result['cleanup'] = $service->cleanupOrphanFaceFiles($dryRun);
        }

        if ($json) {
            CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        CLI::write('Status geral: ' . strtoupper((string) ($result['status'] ?? 'error')));
        foreach (($result['checks'] ?? []) as $name => $check) {
            CLI::write(sprintf('- %s: %s — %s', $name, strtoupper((string) ($check['status'] ?? 'error')), (string) ($check['message'] ?? '')));
        }
        if ($cleanup) {
            CLI::write('Cleanup órfãos: ' . (($result['cleanup']['dry_run'] ?? true) ? 'DRY-RUN' : 'EXECUTADO'));
            CLI::write('Arquivos órfãos detectados: ' . (string) count($result['cleanup']['orphan_candidates'] ?? []));
            CLI::write('Arquivos removidos: ' . (string) ($result['cleanup']['deleted_count'] ?? 0));
        }
    }
}

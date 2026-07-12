<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Config\Installer;
use Config\ProcessSafety;
use RuntimeException;

class InstallerDependencyAutomationService
{
    public function plan(): array
    {
        $config = config(Installer::class);
        $capabilities = (new InstallerExecutionCapabilityService())->inspect();

        $steps = [
            $this->composerInstallStep($config, $capabilities),
            $this->nodeInstallStep($config, $capabilities),
            $this->frontendBuildStep($config, $capabilities),
            $this->writableBootstrapStep($config),
        ];

        return [
            'status' => $this->statusFromSteps($steps),
            'generated_at' => date(DATE_ATOM),
            'project_root' => rtrim($config->projectRoot, DIRECTORY_SEPARATOR),
            'steps' => $steps,
        ];
    }

    public function execute(bool $dryRun = false): array
    {
        $this->assertAutomationExecutionAllowed();

        $plan = $this->plan();
        $config = config(Installer::class);
        $dir = rtrim($config->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'build/support';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $results = [];
        $overall = 'ok';
        foreach ($plan['steps'] as $step) {
            $command = (string) ($step['command'] ?? '');
            $stepName = (string) ($step['name'] ?? 'step');
            $logBase = $dir . '/installer-deps-' . preg_replace('/[^a-z0-9\-]+/i', '-', $stepName) . '-' . date('Ymd-His');

            if (($step['severity'] ?? 'info') === 'blocker') {
                $results[] = array_merge($step, [
                    'result' => 'blocked',
                    'details' => 'A etapa foi bloqueada pelo pré-check e não pode ser executada automaticamente.',
                    'stdout_log' => null,
                    'stderr_log' => null,
                    'exit_code' => null,
                ]);
                $overall = 'blocker';
                continue;
            }

            if ($dryRun || $command === '') {
                $results[] = array_merge($step, [
                    'result' => $dryRun ? 'dry-run' : 'skipped',
                    'details' => $dryRun ? 'Simulação apenas; nenhum comando foi executado.' : 'Etapa sem comando executável.',
                    'stdout_log' => null,
                    'stderr_log' => null,
                    'exit_code' => null,
                ]);
                if (($step['severity'] ?? 'info') === 'warning' && $overall === 'ok') {
                    $overall = 'warning';
                }
                continue;
            }

            $result = $this->runShellCommand($command, rtrim($config->projectRoot, DIRECTORY_SEPARATOR), $logBase, (int) $config->automationTimeout);
            $severity = $result['exit_code'] === 0 ? 'ok' : (($step['optional'] ?? false) ? 'warning' : 'blocker');
            $results[] = array_merge($step, [
                'result' => $result['exit_code'] === 0 ? 'executed' : 'failed',
                'details' => $result['exit_code'] === 0 ? 'Etapa concluída com sucesso.' : 'O comando retornou código não zero. Consulte os logs.',
                'stdout_log' => $result['stdout_log'],
                'stderr_log' => $result['stderr_log'],
                'exit_code' => $result['exit_code'],
                'severity' => $severity,
            ]);

            if ($severity === 'blocker') {
                $overall = 'blocker';
            } elseif ($severity === 'warning' && $overall === 'ok') {
                $overall = 'warning';
            }
        }

        $report = [
            'status' => $overall,
            'generated_at' => date(DATE_ATOM),
            'dry_run' => $dryRun,
            'plan' => $plan,
            'results' => $results,
        ];

        file_put_contents($dir . '/installer-dependency-automation.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $report;
    }

    private function composerInstallStep(Installer $config, array $capabilities): array
    {
        $canExecute = (bool) ($capabilities['capabilities']['composer']['can_execute'] ?? false);

        return [
            'name' => 'composer-install',
            'label' => 'Instalação das dependências PHP via Composer',
            'severity' => $config->allowComposerAutomation && ! $canExecute ? 'blocker' : 'ok',
            'optional' => false,
            'command' => $config->allowComposerAutomation && $canExecute
                ? escapeshellarg($config->phpBinary) . ' ' . escapeshellarg($config->composerBinary) . ' install --no-dev --optimize-autoloader'
                : '',
            'manual_command' => (string) $config->phpBinary . ' ' . (string) $config->composerBinary . ' install --no-dev --optimize-autoloader',
            'details' => $config->allowComposerAutomation
                ? ($canExecute ? 'Composer detectado e apto para execução automática.' : 'Composer automático solicitado, mas o binário não está disponível.')
                : 'Composer automático desabilitado por configuração; o wizard deverá orientar a execução manual.',
        ];
    }

    private function nodeInstallStep(Installer $config, array $capabilities): array
    {
        $canNode = (bool) ($capabilities['capabilities']['node']['can_execute'] ?? false);
        $canNpm = (bool) ($capabilities['capabilities']['npm']['can_execute'] ?? false);
        $enabled = $config->allowNodeAutomation;

        return [
            'name' => 'npm-install',
            'label' => 'Instalação das dependências frontend via npm',
            'severity' => $enabled && (! $canNode || ! $canNpm) ? 'warning' : 'ok',
            'optional' => true,
            'command' => $enabled && $canNode && $canNpm
                ? escapeshellarg($config->npmBinary) . ' install'
                : '',
            'manual_command' => (string) $config->npmBinary . ' install',
            'details' => $enabled
                ? (($canNode && $canNpm) ? 'Node e npm detectados; o instalador pode preparar o frontend automaticamente.' : 'Node/npm não estão disponíveis para automação; o frontend exigirá etapa manual.')
                : 'Automação Node/NPM desabilitada por configuração.',
        ];
    }

    private function frontendBuildStep(Installer $config, array $capabilities): array
    {
        $canNode = (bool) ($capabilities['capabilities']['node']['can_execute'] ?? false);
        $canNpm = (bool) ($capabilities['capabilities']['npm']['can_execute'] ?? false);
        $enabled = $config->allowNodeAutomation && $config->allowFrontendBuildAutomation;

        return [
            'name' => 'npm-build',
            'label' => 'Build dos assets frontend',
            'severity' => $enabled && (! $canNode || ! $canNpm) ? 'warning' : 'ok',
            'optional' => true,
            'command' => $enabled && $canNode && $canNpm
                ? escapeshellarg($config->npmBinary) . ' run build'
                : '',
            'manual_command' => (string) $config->npmBinary . ' run build',
            'details' => $enabled
                ? (($canNode && $canNpm) ? 'Build frontend poderá ser executado automaticamente.' : 'Build frontend indisponível até que Node/npm estejam acessíveis.')
                : 'Build automático do frontend desabilitado por configuração.',
        ];
    }

    private function writableBootstrapStep(Installer $config): array
    {
        $paths = [];
        foreach ($config->criticalWritableDirectories as $directory) {
            $full = rtrim($config->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
            $paths[] = escapeshellarg($full);
        }

        $command = 'mkdir -p ' . implode(' ', $paths)
            . ' && chown -R www:www ' . implode(' ', $paths)
            . ' && find ' . implode(' ', $paths) . " -type d -exec chmod 775 {} \\;"
            . ' && find ' . implode(' ', $paths) . " -type f -exec chmod 664 {} \\;";

        return [
            'name' => 'writable-bootstrap',
            'label' => 'Bootstrap dos diretórios graváveis da aplicação',
            'severity' => $config->allowWritableBootstrap ? 'ok' : 'warning',
            'optional' => ! $config->allowWritableBootstrap,
            'command' => $config->allowWritableBootstrap ? $command : '',
            'manual_command' => $command,
            'details' => $config->allowWritableBootstrap
                ? 'O instalador pode tentar preparar os diretórios graváveis e permissões mínimas do projeto.'
                : 'Bootstrap automático dos diretórios graváveis desabilitado; a ação precisará ser executada manualmente.',
        ];
    }

    private function statusFromSteps(array $steps): string
    {
        $status = 'ok';
        foreach ($steps as $step) {
            $severity = (string) ($step['severity'] ?? 'ok');
            if ($severity === 'blocker') {
                return 'blocker';
            }
            if ($severity === 'warning') {
                $status = 'warning';
            }
        }

        return $status;
    }

    private function assertAutomationExecutionAllowed(): void
    {
        $processSafety = config(ProcessSafety::class);

        if (! $processSafety->canRunInstallerAutomationInCurrentRuntime()) {
            throw new RuntimeException('Automações do instalador via shell estão bloqueadas para o runtime atual. Execute o comando via CLI segura ou habilite explicitamente o runtime web.');
        }
    }

    private function runShellCommand(string $command, string $cwd, string $logBase, int $timeout): array
    {
        $stdoutLog = $logBase . '.stdout.log';
        $stderrLog = $logBase . '.stderr.log';
        $metaLog = $logBase . '.meta.json';

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutLog, 'w'],
            2 => ['file', $stderrLog, 'w'],
        ];

        $process = @proc_open($command, $descriptor, $pipes, $cwd);
        if (! is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o processo automatizado do instalador.');
        }

        fclose($pipes[0]);
        $start = time();
        $exitCode = null;
        do {
            $status = proc_get_status($process);
            if (! $status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if ((time() - $start) > $timeout) {
                proc_terminate($process, 15);
                $exitCode = 124;
                break;
            }
            usleep(200000);
        } while (true);

        if ($exitCode === null) {
            $exitCode = proc_close($process);
        } else {
            proc_close($process);
        }

        file_put_contents($metaLog, json_encode([
            'command' => $command,
            'cwd' => $cwd,
            'started_at' => date(DATE_ATOM, $start),
            'finished_at' => date(DATE_ATOM),
            'timeout_seconds' => $timeout,
            'exit_code' => $exitCode,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return [
            'stdout_log' => $stdoutLog,
            'stderr_log' => $stderrLog,
            'meta_log' => $metaLog,
            'exit_code' => $exitCode,
        ];
    }
}

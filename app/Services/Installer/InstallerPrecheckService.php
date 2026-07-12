<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Config\Installer;

class InstallerPrecheckService
{
    public function build(bool $withConnections = true): array
    {
        $config = config(Installer::class);
        $doctor = (new InstallationDoctorService())->inspect($withConnections);
        $capabilities = (new InstallerExecutionCapabilityService())->inspect();

        $requiredExtensions = [];
        foreach ($config->requiredPhpExtensions as $extension) {
            $requiredExtensions[] = [
                'name' => $extension,
                'loaded' => extension_loaded($extension),
            ];
        }

        $writable = [];
        foreach ($config->criticalWritableDirectories as $directory) {
            $path = rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
            $writable[] = [
                'directory' => $directory,
                'path' => $path,
                'exists' => is_dir($path),
                'writable' => is_dir($path) && is_writable($path),
            ];
        }

        $sections = [
            'php' => $this->sectionStatus(array_merge(
                [['value' => PHP_VERSION, 'severity' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'blocker']],
                array_map(static fn (array $item): array => [
                    'value' => $item['name'],
                    'severity' => $item['loaded'] ? 'ok' : 'blocker',
                ], $requiredExtensions)
            )),
            'filesystem' => $this->sectionStatus(array_map(static fn (array $item): array => [
                'value' => $item['directory'],
                'severity' => ($item['exists'] && $item['writable']) ? 'ok' : 'blocker',
            ], $writable)),
            'automation' => (string) ($capabilities['status'] ?? 'warning'),
            'database' => $this->findCheckSeverity($doctor['checks'] ?? [], 'database'),
            'redis' => $this->findCheckSeverity($doctor['checks'] ?? [], 'redis'),
            'secrets' => $this->sectionStatus(array_values(array_filter(
                $doctor['checks'] ?? [],
                static fn (array $check): bool => str_starts_with((string) ($check['key'] ?? ''), 'secret_') || (string) ($check['key'] ?? '') === 'encryption_key'
            ))),
        ];

        $nextActions = $this->nextActions($doctor, $capabilities, $writable, $requiredExtensions);

        return [
            'status' => $this->aggregateStatus($sections, (string) ($doctor['status'] ?? 'blocker'), (string) ($capabilities['status'] ?? 'warning')),
            'generated_at' => date(DATE_ATOM),
            'summary' => [
                'doctor_status' => (string) ($doctor['status'] ?? 'blocker'),
                'capability_status' => (string) ($capabilities['status'] ?? 'warning'),
                'sections' => $sections,
            ],
            'environment' => [
                'php_version' => PHP_VERSION,
                'required_php_extensions' => $requiredExtensions,
                'critical_writable_directories' => $writable,
            ],
            'automation' => $capabilities,
            'doctor' => $doctor,
            'messages' => [
                'principles' => [
                    'Bloqueadores devem impedir a continuação do wizard automático.',
                    'Avisos devem explicar claramente o impacto e a ação corretiva recomendada.',
                    'Toda dependência ausente deve informar o pacote/extensão/binário esperado.',
                    'Saídas automáticas devem ser persistidas em writable/logs e build/support.',
                ],
            ],
            'next_actions' => $nextActions,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function sectionStatus(array $items): string
    {
        $status = 'ok';
        foreach ($items as $item) {
            $severity = (string) ($item['severity'] ?? 'ok');
            if ($severity === 'blocker') {
                return 'blocker';
            }
            if ($severity === 'warning') {
                $status = 'warning';
            }
        }
        return $status;
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function findCheckSeverity(array $checks, string $key): string
    {
        foreach ($checks as $check) {
            if ((string) ($check['key'] ?? '') === $key) {
                return (string) ($check['severity'] ?? 'warning');
            }
        }

        return 'warning';
    }

    /**
     * @param array<string, mixed> $doctor
     * @param array<string, mixed> $capabilities
     * @param array<int, array<string, mixed>> $writable
     * @param array<int, array<string, mixed>> $requiredExtensions
     * @return list<array{severity:string,title:string,details:string,command:?string}>
     */
    private function nextActions(array $doctor, array $capabilities, array $writable, array $requiredExtensions): array
    {
        $actions = [];

        foreach ($requiredExtensions as $extension) {
            if (($extension['loaded'] ?? false) !== true) {
                $actions[] = [
                    'severity' => 'blocker',
                    'title' => 'Habilite a extensão PHP ' . $extension['name'],
                    'details' => 'O instalador automático depende desta extensão para validar ambiente, banco ou geração de relatórios.',
                    'command' => null,
                ];
            }
        }

        foreach ($writable as $directory) {
            if (($directory['exists'] ?? false) !== true || ($directory['writable'] ?? false) !== true) {
                $actions[] = [
                    'severity' => 'blocker',
                    'title' => 'Corrija permissões de ' . $directory['directory'],
                    'details' => 'O diretório crítico não existe ou não é gravável para o usuário do PHP-FPM/CLI.',
                    'command' => 'mkdir -p ' . escapeshellarg((string) $directory['path']) . ' && chown -R www:www ' . escapeshellarg((string) $directory['path']),
                ];
            }
        }

        if (($capabilities['capabilities']['composer']['allowed'] ?? false) === true && ($capabilities['capabilities']['composer']['can_execute'] ?? false) !== true) {
            $actions[] = [
                'severity' => 'warning',
                'title' => 'Composer automático indisponível',
                'details' => 'O wizard poderá orientar a instalação manual das dependências, mas não conseguirá executar composer sozinho.',
                'command' => (string) (($capabilities['capabilities']['composer']['binary']['path'] ?? '') ?: '/usr/local/bin/composer') . ' install --no-dev --optimize-autoloader',
            ];
        }

        if (($capabilities['capabilities']['node']['allowed'] ?? false) === true && (($capabilities['capabilities']['node']['can_execute'] ?? false) !== true || ($capabilities['capabilities']['npm']['can_execute'] ?? false) !== true)) {
            $actions[] = [
                'severity' => 'warning',
                'title' => 'Node/NPM automáticos indisponíveis',
                'details' => 'Assets frontend precisarão ser gerados manualmente antes da conclusão da instalação.',
                'command' => 'npm install && npm run build',
            ];
        }

        foreach (($doctor['checks'] ?? []) as $check) {
            if ((string) ($check['severity'] ?? 'ok') !== 'blocker') {
                continue;
            }
            $key = (string) ($check['key'] ?? '');
            if ($key === 'database') {
                $actions[] = [
                    'severity' => 'blocker',
                    'title' => 'Corrija a configuração do banco de dados',
                    'details' => (string) ($check['details'] ?? 'Hostname, database e username são obrigatórios.'),
                    'command' => null,
                ];
            }
            if ($key === 'session_save_path') {
                $actions[] = [
                    'severity' => 'blocker',
                    'title' => 'Corrija o session.save_path',
                    'details' => (string) ($check['details'] ?? 'Diretório de sessão indisponível.'),
                    'command' => null,
                ];
            }
        }

        if ($actions === []) {
            $actions[] = [
                'severity' => 'info',
                'title' => 'Ambiente apto para o wizard automático',
                'details' => 'Nenhum bloqueador foi detectado no pré-check desta arquitetura.',
                'command' => null,
            ];
        }

        return $actions;
    }

    private function aggregateStatus(array $sections, string $doctorStatus, string $capabilityStatus): string
    {
        if (in_array('blocker', array_values($sections), true) || $doctorStatus === 'blocker') {
            return 'blocker';
        }

        if (in_array('warning', array_values($sections), true) || $capabilityStatus === 'warning' || $doctorStatus === 'warning') {
            return 'warning';
        }

        return 'ok';
    }
}

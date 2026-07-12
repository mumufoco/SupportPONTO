<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Config\Installer;
use Config\ProcessSafety;

class InstallerExecutionCapabilityService
{
    public function inspect(): array
    {
        $config = config(Installer::class);
        $processSafety = config(ProcessSafety::class);

        $composer = $this->inspectBinary($config->composerBinary);
        $php = $this->inspectBinary($config->phpBinary);
        $node = $this->inspectBinary($config->nodeBinary);
        $npm = $this->inspectBinary($config->npmBinary);

        $disableFunctions = array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', (string) ini_get('disable_functions'))
        ));

        $capabilities = [
            'composer' => [
                'allowed' => $config->allowComposerAutomation,
                'binary' => $composer,
                'can_execute' => $config->allowComposerAutomation && $composer['available'],
            ],
            'node' => [
                'allowed' => $config->allowNodeAutomation,
                'binary' => $node,
                'can_execute' => $config->allowNodeAutomation && $node['available'],
            ],
            'npm' => [
                'allowed' => $config->allowNodeAutomation,
                'binary' => $npm,
                'can_execute' => $config->allowNodeAutomation && $npm['available'],
            ],
            'php' => [
                'binary' => $php,
                'can_execute' => $php['available'],
            ],
            'writable_bootstrap' => [
                'allowed' => $config->allowWritableBootstrap,
            ],
            'process' => [
                'runtime' => $processSafety->currentRuntimeLabel(),
                'shell_policy' => $processSafety->canExecuteShellInCurrentRuntime(),
                'shell_exec' => function_exists('shell_exec') && ! in_array('shell_exec', $disableFunctions, true),
                'proc_open' => function_exists('proc_open') && ! in_array('proc_open', $disableFunctions, true),
                'exec' => function_exists('exec') && ! in_array('exec', $disableFunctions, true),
                'disable_functions' => array_values($disableFunctions),
            ],
        ];

        return [
            'status' => $this->overallStatus($capabilities),
            'capabilities' => $capabilities,
        ];
    }

    private function inspectBinary(string $binary): array
    {
        $binary = trim($binary);
        if ($binary === '') {
            return [
                'path' => '',
                'available' => false,
                'details' => 'Binário não configurado.',
            ];
        }

        $processSafety = config(ProcessSafety::class);

        if (str_contains($binary, '/')) {
            return [
                'path' => $binary,
                'available' => is_file($binary) && is_executable($binary),
                'details' => (is_file($binary) && is_executable($binary)) ? 'Binário encontrado.' : 'Binário configurado, mas não executável ou ausente.',
            ];
        }

        if (! $processSafety->canDiscoverBinariesInCurrentRuntime()) {
            return [
                'path' => $binary,
                'available' => false,
                'details' => is_cli()
                    ? 'Descoberta de binário via shell indisponível no runtime CLI atual.'
                    : 'Descoberta de binário via shell desabilitada no runtime web por política de segurança.',
            ];
        }

        $resolved = trim((string) @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));

        return [
            'path' => $resolved !== '' ? $resolved : $binary,
            'available' => $resolved !== '',
            'details' => $resolved !== '' ? 'Binário resolvido no PATH.' : 'Binário não encontrado no PATH.',
        ];
    }

    private function overallStatus(array $capabilities): string
    {
        if (($capabilities['php']['can_execute'] ?? false) !== true) {
            return 'blocker';
        }

        if (($capabilities['composer']['allowed'] ?? false) === true && ($capabilities['composer']['can_execute'] ?? false) !== true) {
            return 'warning';
        }

        if (($capabilities['node']['allowed'] ?? false) === true && (($capabilities['node']['can_execute'] ?? false) !== true || ($capabilities['npm']['can_execute'] ?? false) !== true)) {
            return 'warning';
        }

        return 'ok';
    }
}

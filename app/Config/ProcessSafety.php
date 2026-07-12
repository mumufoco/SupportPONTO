<?php

declare(strict_types=1);

namespace Config;

use App\Support\BootstrapEnv;
use CodeIgniter\Config\BaseConfig;

class ProcessSafety extends BaseConfig
{
    /**
     * Permite execução de shell/processos no runtime CLI.
     */
    public bool $allowCliShellExecution = true;

    /**
     * Mantido desabilitado por padrão. Permite execução de shell/processos em runtime web.
     */
    public bool $allowWebShellExecution = false;

    /**
     * Mantido desabilitado por padrão. Permite descoberta de binários via shell em runtime web.
     */
    public bool $allowWebBinaryDiscovery = false;

    /**
     * Mantido desabilitado por padrão. Permite backup/restauração via runtime web.
     */
    public bool $allowWebDatabaseBackup = false;

    /**
     * Mantido desabilitado por padrão. Permite automações do instalador via runtime web.
     */
    public bool $allowWebInstallerAutomation = false;

    public function __construct()
    {
        parent::__construct();

        $this->allowCliShellExecution = BootstrapEnv::bool('PROCESS_ALLOW_CLI_SHELL', $this->allowCliShellExecution);
        $this->allowWebShellExecution = BootstrapEnv::bool('PROCESS_ALLOW_WEB_SHELL', $this->allowWebShellExecution);
        $this->allowWebBinaryDiscovery = BootstrapEnv::bool('PROCESS_ALLOW_WEB_BINARY_DISCOVERY', $this->allowWebBinaryDiscovery);
        $this->allowWebDatabaseBackup = BootstrapEnv::bool('BACKUP_ALLOW_WEB_RUNTIME', $this->allowWebDatabaseBackup);
        $this->allowWebInstallerAutomation = BootstrapEnv::bool('INSTALLER_ALLOW_WEB_AUTOMATION', $this->allowWebInstallerAutomation);
    }

    public function canExecuteShellInCurrentRuntime(): bool
    {
        return is_cli() ? $this->allowCliShellExecution : $this->allowWebShellExecution;
    }

    public function canDiscoverBinariesInCurrentRuntime(): bool
    {
        return is_cli() ? $this->allowCliShellExecution : ($this->allowWebShellExecution && $this->allowWebBinaryDiscovery);
    }

    public function canRunDatabaseBackupInCurrentRuntime(): bool
    {
        return is_cli() ? $this->allowCliShellExecution : ($this->allowWebShellExecution && $this->allowWebDatabaseBackup);
    }

    public function canRunInstallerAutomationInCurrentRuntime(): bool
    {
        return is_cli() ? $this->allowCliShellExecution : ($this->allowWebShellExecution && $this->allowWebInstallerAutomation);
    }

    public function currentRuntimeLabel(): string
    {
        return is_cli() ? 'cli' : 'web';
    }
}

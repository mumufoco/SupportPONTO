<?php

declare(strict_types=1);

namespace Config;

use App\Support\BootstrapEnv;
use CodeIgniter\Config\BaseConfig;

class Installer extends BaseConfig
{
    /**
     * Habilita apenas a execução do launcher web temporário publicado manualmente em public/install.php.
     */
    public bool $allowWebInstaller = false;

    /**
     * Token obrigatório para liberar o primeiro acesso ao instalador web.
     */
    public string $token = '';

    /**
     * Lista de IPs autorizados a acessar o instalador. Vazio = sem restrição adicional.
     *
     * @var list<string>
     */
    public array $allowedIps = [];

    /**
     * Desabilitado por padrão. Habilita a tentativa automática de executar Composer.
     */
    public bool $allowComposerAutomation = false;

    /**
     * Desabilitado por padrão. Habilita a tentativa automática de executar npm/node.
     */
    public bool $allowNodeAutomation = false;

    /**
     * Desabilitado por padrão. Habilita a tentativa automática de ajustar permissões do projeto.
     */
    public bool $allowWritableBootstrap = false;

    /**
     * Desabilitado por padrão. Habilita a tentativa automática de executar o build frontend da aplicação.
     */
    public bool $allowFrontendBuildAutomation = false;

    /**
     * Comando Composer preferencial.
     */
    public string $composerBinary = '/usr/local/bin/composer';

    /**
     * Binário PHP CLI preferencial.
     */
    public string $phpBinary = PHP_BINARY;

    /**
     * Binário Node preferencial.
     */
    public string $nodeBinary = 'node';

    /**
     * Binário npm preferencial.
     */
    public string $npmBinary = 'npm';

    /**
     * Diretório raiz do projeto para automação das dependências.
     */
    public string $projectRoot = ROOTPATH;

    /**
     * Timeout padrão por etapa automatizada, em segundos.
     */
    public int $automationTimeout = 1800;


    /**
     * Desabilita automaticamente o instalador web após sucesso.
     */
    public bool $autoDisableWebInstaller = true;

    /**
     * Exige lock de instalação para considerar a homologação final aprovada.
     */
    public bool $requireInstallationLock = true;

    /**
     * Relatório final de homologação do instalador.
     */
    public string $finalizationReportPath = 'build/support/installer-finalization.json';

    /**
     * Diretórios críticos para permissão de escrita.
     *
     * @var list<string>
     */
    public array $criticalWritableDirectories = [
        'writable',
        'writable/cache',
        'writable/logs',
        'writable/session',
        'writable/uploads',
        'writable/installer',
        'storage',
    ];

    /**
     * Extensões PHP mínimas para suporte WordPress-like installer.
     *
     * @var list<string>
     */
    public array $requiredPhpExtensions = [
        'curl',
        'fileinfo',
        'intl',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_pgsql',
        'pgsql',
        'xml',
        'zip',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->allowWebInstaller = BootstrapEnv::bool('ALLOW_WEB_INSTALLER', $this->allowWebInstaller);
        $this->token = (string) (BootstrapEnv::get('INSTALLER_TOKEN', BootstrapEnv::get('INSTALLER_WEB_TOKEN', $this->token)) ?? '');
        $this->allowedIps = BootstrapEnv::csv('INSTALLER_ALLOWED_IPS', BootstrapEnv::csv('INSTALLER_WEB_ALLOWED_IPS', $this->allowedIps));
        $this->allowComposerAutomation = BootstrapEnv::bool('INSTALLER_ALLOW_COMPOSER_AUTOMATION', $this->allowComposerAutomation);
        $this->allowNodeAutomation = BootstrapEnv::bool('INSTALLER_ALLOW_NODE_AUTOMATION', $this->allowNodeAutomation);
        $this->allowWritableBootstrap = BootstrapEnv::bool('INSTALLER_ALLOW_WRITABLE_BOOTSTRAP', $this->allowWritableBootstrap);
        $this->allowFrontendBuildAutomation = BootstrapEnv::bool('INSTALLER_ALLOW_FRONTEND_BUILD_AUTOMATION', $this->allowFrontendBuildAutomation);
        $this->autoDisableWebInstaller = BootstrapEnv::bool('INSTALLER_AUTO_DISABLE_WEB', $this->autoDisableWebInstaller);
        $this->requireInstallationLock = BootstrapEnv::bool('INSTALLER_REQUIRE_LOCK', $this->requireInstallationLock);
        $this->composerBinary = (string) (BootstrapEnv::get('INSTALLER_COMPOSER_BIN', $this->composerBinary) ?? $this->composerBinary);
        $this->phpBinary = (string) (BootstrapEnv::get('INSTALLER_PHP_BIN', $this->phpBinary) ?? $this->phpBinary);
        $this->nodeBinary = (string) (BootstrapEnv::get('INSTALLER_NODE_BIN', $this->nodeBinary) ?? $this->nodeBinary);
        $this->npmBinary = (string) (BootstrapEnv::get('INSTALLER_NPM_BIN', $this->npmBinary) ?? $this->npmBinary);
        $this->projectRoot = rtrim((string) (BootstrapEnv::get('INSTALLER_PROJECT_ROOT', $this->projectRoot) ?? $this->projectRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->automationTimeout = max(60, (int) (BootstrapEnv::int('INSTALLER_AUTOMATION_TIMEOUT', $this->automationTimeout) ?? $this->automationTimeout));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Support\ReleaseMetadata;
use Config\Installer;

class AutomaticInstallerArchitectureService
{
    public function blueprint(): array
    {
        $config = config(Installer::class);
        $capabilities = (new InstallerExecutionCapabilityService())->inspect();

        $release = ReleaseMetadata::read();

        return [
            'version' => (string) ($release['version'] ?? '1.1.425'),
            'package' => (int) ($release['package'] ?? 425),
            'name' => 'SupportPONTO Automatic Installer Blueprint',
            'status' => $capabilities['status'],
            'generated_at' => date('c'),
            'contract' => [
                'entrypoint' => '/install.php (somente após publicação temporária do launcher)',
                'allow_web_installer_env' => 'ALLOW_WEB_INSTALLER',
                'token_env' => 'INSTALLER_TOKEN',
                'allowed_ips_env' => 'INSTALLER_ALLOWED_IPS',
                'deprecated_token_env' => 'INSTALLER_WEB_TOKEN',
                'deprecated_allowed_ips_env' => 'INSTALLER_WEB_ALLOWED_IPS',
                'canonical_mode' => 'published-temporary-launcher',
                'lock_file' => 'writable/installer/installed.lock',
            ],
            'modes' => [
                [
                    'key' => 'assisted',
                    'label' => 'Instalação assistida',
                    'description' => 'O instalador valida o ambiente, orienta correções e executa apenas as etapas suportadas no servidor.',
                ],
                [
                    'key' => 'automatic-application',
                    'label' => 'Instalação automática da aplicação',
                    'description' => 'O instalador tenta executar Composer, npm/build e bootstrap do projeto quando o ambiente permitir.',
                ],
                [
                    'key' => 'server-bootstrap-required',
                    'label' => 'Bootstrap do servidor obrigatório',
                    'description' => 'Dependências do sistema operacional, extensões PHP e serviços base continuam sob responsabilidade de SSH root/aaPanel.',
                ],
            ],
            'steps' => [
                [
                    'key' => 'welcome',
                    'label' => 'Boas-vindas',
                    'goal' => 'Apresentar o contrato do instalador e o estado geral do ambiente.',
                ],
                [
                    'key' => 'server-check',
                    'label' => 'Servidor e PHP',
                    'goal' => 'Validar PHP, extensões, binários e permissões críticas.',
                    'checks' => $config->requiredPhpExtensions,
                ],
                [
                    'key' => 'database',
                    'label' => 'Banco de dados',
                    'goal' => 'Testar a conexão, validar credenciais e confirmar a estratégia de instalação.',
                ],
                [
                    'key' => 'application-dependencies',
                    'label' => 'Dependências da aplicação',
                    'goal' => 'Executar Composer, npm e build quando permitido; caso contrário, orientar comandos exatos.',
                ],
                [
                    'key' => 'application-config',
                    'label' => 'Configuração do sistema',
                    'goal' => 'Gerar .env, validar baseURL, cache, Redis e demais chaves essenciais.',
                ],
                [
                    'key' => 'admin',
                    'label' => 'Administrador inicial',
                    'goal' => 'Cadastrar o primeiro administrador com política de senha e validações de segurança.',
                ],
                [
                    'key' => 'install',
                    'label' => 'Instalação',
                    'goal' => 'Executar migrations, seeders e provisionamento inicial com logs detalhados.',
                ],
                [
                    'key' => 'complete',
                    'label' => 'Conclusão',
                    'goal' => 'Apresentar resumo, próximos passos e bloquear o instalador.',
                ],
            ],
            'automation' => [
                'composer' => [
                    'enabled' => $config->allowComposerAutomation,
                    'binary' => $config->composerBinary,
                    'capability' => $capabilities['capabilities']['composer'] ?? [],
                ],
                'node' => [
                    'enabled' => $config->allowNodeAutomation,
                    'node_binary' => $config->nodeBinary,
                    'npm_binary' => $config->npmBinary,
                    'capability' => [
                        'node' => $capabilities['capabilities']['node'] ?? [],
                        'npm' => $capabilities['capabilities']['npm'] ?? [],
                    ],
                ],
                'writable_bootstrap' => [
                    'enabled' => $config->allowWritableBootstrap,
                    'directories' => $config->criticalWritableDirectories,
                ],
            ],
            'messages' => [
                'principles' => [
                    'Mensagens públicas devem explicar claramente o problema, o impacto e a ação sugerida.',
                    'Detalhes técnicos completos devem ser gravados em writable/logs/installer-*.log.',
                    'O instalador deve diferenciar blocker, warning e info em todas as etapas.',
                    'Toda tentativa automática deve informar o comando executado e o exit code.',
                ],
            ],
            'deliverables' => [
                'build/support/installer-blueprint.json',
                'writable/logs/installer-YYYY-MM-DD.log',
                'writable/installer/installed.lock',
            ],
        ];
    }
}

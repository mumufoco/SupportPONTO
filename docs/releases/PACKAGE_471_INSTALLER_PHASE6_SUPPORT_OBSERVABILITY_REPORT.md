# Package 471 — Installer Phase 6 Support Observability Report

## Resumo

Pacote de evolução do instalador do SupportPONTO para suporte técnico, diagnóstico e observabilidade.

## Alterações principais

- Versão sincronizada para `1.1.476`.
- Instalador marcado como `1.1.476-installer-database-stabilization`.
- Logs do instalador agora carregam `install_id` e `installer_version`.
- Criado stream NDJSON em `writable/installer/events-YYYY-MM-DD.ndjson`.
- Criado comando `--installer-doctor`.
- Criado comando `--support-bundle`.
- Bundle sanitizado inclui relatórios, journal, eventos, logs e metadados de versão quando disponíveis.
- Bundle usa `ZipArchive` quando disponível e fallback para binário `zip` quando a extensão PHP não estiver ativa.

## Critérios de validação

- `php -l tools/installer/SupportPontoZeroInstaller.php`
- `php tests/Feature/InstallerPhase6SupportObservabilityStaticTest.php`
- `php tools/installer/install_cli.php --installer-doctor --json`
- `php tools/installer/install_cli.php --support-bundle --json`
- `unzip -t` do pacote final

# Pacote 442 — Provisionamento de servidor e dependências

## Objetivo

Separar corretamente o que o instalador PHP consegue fazer do que depende de permissões de sistema (`root`/`sudo`).

## Problema resolvido

Nenhum instalador PHP rodando pelo processo web deve prometer instalar pacotes do sistema, extensões do PHP, serviços do PostgreSQL ou reiniciar PHP-FPM/Nginx/Apache sem permissão adequada. O pacote cria um fluxo previsível: primeiro provisiona o servidor via SSH/CLI, depois executa o instalador da aplicação.

## Arquivos alterados/criados

- `install/runtime/provision-server.sh`
- `tools/installer/SupportPontoZeroInstaller.php`
- `README_INSTALL.md`
- `docs/deployment/PROVISIONAMENTO_SERVIDOR_UBUNTU_AAPANEL.md`
- `docs/releases/PACOTE_442_PROVISIONAMENTO_SERVIDOR_DEPENDENCIAS.md`
- `tests/Feature/Package442ServerProvisioningStaticTest.php`
- `public/version.json`

## Implementações

- Script de provisionamento para Ubuntu/aaPanel.
- Diagnóstico de extensões PHP obrigatórias.
- Diagnóstico de PostgreSQL client (`psql`/`pg_dump`).
- Instalação opcional de Composer local.
- Ajuste opcional de permissões de `writable/`.
- Diagnóstico de PHP-FPM, Nginx e Apache.
- Documentação de comandos para servidor novo.
- Integração do diagnóstico do instalador com o script.
- `--provision-server` agora encaminha argumentos para o script real.
- Diagnóstico JSON do instalador inclui categoria `server_provisioning`.

## Validação

- `bash -n install/runtime/provision-server.sh`.
- `php -l tools/installer/SupportPontoZeroInstaller.php`.
- `bash install/runtime/provision-server.sh --diagnose --dry-run`.
- `php tools/installer/install_cli.php --provision-server --diagnose`.
- `php tools/installer/install_cli.php --diagnose`.

## Resultado esperado

Servidor novo pode ser preparado com um script CLI claro, enquanto o instalador PHP continua abrindo limpo, sem fatal error, explicando qualquer bloqueio de ambiente e sem tentar executar ações que exigem root/sudo no processo web.

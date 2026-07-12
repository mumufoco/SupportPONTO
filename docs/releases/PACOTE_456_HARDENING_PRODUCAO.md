# Pacote 456 — Hardening de produção

## Objetivo

Preparar o SupportPONTO para produção, reduzindo exposição de debug, instalador, arquivos sensíveis e diretórios internos.

## Alterações principais

- Criado guard independente do Composer para o instalador web.
- Instalador web bloqueia acesso quando `installed.lock` existe.
- Em produção, acesso ao instalador exige `ALLOW_WEB_INSTALLER=true`, `INSTALLER_PRODUCTION_WEB_OVERRIDE=true`, `INSTALLER_TOKEN` e, quando configurado, IP autorizado.
- `.env.example` e `.env.production.example` reforçados com debug desativado e flags de hardening.
- `.htaccess` raiz e `public/.htaccess` reforçados contra exposição de diretórios internos e arquivos sensíveis.
- `writable/` e subdiretórios críticos receberam `.htaccess` de negação total.
- Criada configuração `Config\ProductionHardening`.
- Criado gate `tools/quality/production-hardening-audit.php`.
- `test:release-critical` passou a incluir hardening de produção.

## Validação

- `php -l` nos arquivos PHP alterados/criados.
- `php tools/quality/production-hardening-audit.php`.
- `bash scripts/testing/essential-regression-gate.sh`.
- `bash scripts/testing/production-hardening-gate.sh`.
- `unzip -t` no pacote final.

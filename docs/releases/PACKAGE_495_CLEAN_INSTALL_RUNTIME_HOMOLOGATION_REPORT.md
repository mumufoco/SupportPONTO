# Package 495 — Clean Install Runtime Homologation

Release: **SupportPONTO v1.1.496**

## Objetivo

Consolidar a homologação runtime do instalador automático em PostgreSQL limpo, permitindo validar instalação real com banco zerado, migrations, seeders, `installed.lock`, health check, login smoke e tabelas críticas.

## Arquivos principais

- `docker-compose.installer-test.yml`
- `docker-compose.clean-install.yml`
- `tools/release/run-clean-install-smoke.php`
- `tools/release/audit-clean-install-runtime.php`
- `tools/testing/clean-install-e2e.sh`
- `tools/testing/clean-install-postcheck.php`

## Melhorias aplicadas

1. Runner atualizado para pacote 495 e versão 1.1.496.
2. E2E passa a usar `docker-compose.installer-test.yml` como compose oficial.
3. Serviço PostgreSQL oficial do smoke: `postgres-installer-test`.
4. Auditoria runtime exige relatório do pacote 495.
5. Relatório declara escopo de homologação: PostgreSQL limpo, instalador CLI, `.env`, migrations, seeders, lock, health, login smoke e tabelas.
6. Credenciais de teste atualizadas para `SupportPontoCleanInstall#495`.

## Gates executados

- Lint PHP dos scripts de release.
- Lint Bash do E2E.
- `run-clean-install-smoke.php --json --dry-run`.
- `audit-clean-install-runtime.php --json` contra relatório dry-run.
- Gates de instalador e versão.

## Limitação transparente

A execução real completa depende de Docker/PostgreSQL/Composer. Quando esses recursos não estão disponíveis no ambiente de empacotamento, apenas o `dry-run` e os contratos estáticos são executados.

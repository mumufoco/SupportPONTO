# Fase 25 — Homologação real do instalador em ambiente limpo

## Versão

SupportPONTO v1.1.492

## Objetivo

Transformar a validação do instalador automático em um teste executável de ponta a ponta, usando PostgreSQL zerado, workspace limpo, migrations reais, seeders reais e verificações pós-instalação.

## Escopo entregue

- `docker-compose.installer-test.yml`: compose oficial de homologação do instalador.
- `docker-compose.clean-install.yml`: alias legado alinhado ao pacote 490.
- `tools/release/run-clean-install-smoke.php`: runner do smoke real ou dry-run.
- `tools/release/audit-clean-install-runtime.php`: auditoria runtime do relatório e do banco.
- `tools/testing/clean-install-e2e.sh`: atualizado para Pacote 490.
- `tests/Feature/InstallerPhase25CleanInstallHomologationStaticTest.php`: gate estático do contrato.

## Como executar homologação real

```bash
php tools/release/run-clean-install-smoke.php --json --php-bin=/www/server/php/83/bin/php
```

Quando necessário, pule o smoke HTTP:

```bash
php tools/release/run-clean-install-smoke.php --json --skip-http --php-bin=/www/server/php/83/bin/php
```

## Como executar validação sem Docker

```bash
php tools/release/run-clean-install-smoke.php --json --dry-run
```

O dry-run não comprova instalação real. Ele valida apenas se o pacote contém todos os artefatos necessários para a homologação.

## Relatórios gerados

- `writable/installer/clean-install-smoke-last.json`
- `writable/testing/clean-install/reports/clean-install-<run_id>.json`
- `writable/testing/clean-install/reports/runtime-audit-<run_id>.json`

## Validações runtime previstas

- PostgreSQL limpo saudável.
- Vendor instalado ou presente.
- Instalador CLI executado em banco vazio.
- `.env` criado.
- `installed.lock` criado.
- `spark migrate:status` executado.
- Rotas carregadas.
- `/healthz` responde quando smoke HTTP está ativo.
- Tela de login carrega.
- Tabelas críticas existem:
  - `migrations`
  - `employees`
  - `time_punches`
  - `settings`
  - `audit_logs`

## Observação operacional

Esta fase cria o contrato de homologação real. Em ambientes sem Docker, Composer ou PostgreSQL disponíveis, execute somente o dry-run e rode a homologação real no servidor/build apropriado.

# Fase 30 — Homologação runtime em PostgreSQL limpo

Versão: **SupportPONTO v1.1.496**

## Objetivo

Fortalecer a homologação real do instalador em ambiente limpo, garantindo que o fluxo de instalação possa ser validado contra um PostgreSQL zerado antes de liberar o pacote para produção.

## Escopo

- Atualização do runner `tools/release/run-clean-install-smoke.php` para pacote 495.
- Atualização do E2E `tools/testing/clean-install-e2e.sh` para usar o compose oficial `docker-compose.installer-test.yml` por padrão.
- Atualização da auditoria `tools/release/audit-clean-install-runtime.php` para exigir relatório do pacote 495 e versão 1.1.496.
- Validação de tabelas críticas: `migrations`, `employees`, `time_punches`, `settings` e `audit_logs`.
- Contrato de execução com relatório `writable/installer/clean-install-smoke-last.json`.

## Execução recomendada

```bash
php tools/release/run-clean-install-smoke.php --json --php-bin=/www/server/php/83/bin/php
```

Para executar sem smoke HTTP:

```bash
php tools/release/run-clean-install-smoke.php --json --skip-http --php-bin=/www/server/php/83/bin/php
```

Para validar apenas o contrato estrutural:

```bash
php tools/release/run-clean-install-smoke.php --json --dry-run
```

## Auditoria runtime direta

```bash
php tools/release/audit-clean-install-runtime.php \
  --json \
  --report=writable/installer/clean-install-smoke-last.json \
  --db-dsn='pgsql:host=127.0.0.1;port=55432;dbname=supportponto_clean' \
  --db-user=supportponto \
  --db-pass='SupportPontoCleanInstall#495'
```

## Observação

Esta fase não substitui a execução real em servidor/CI com Docker, PostgreSQL e Composer disponíveis. O `dry-run` valida o contrato do pacote, mas não comprova migrations/seeders em runtime.

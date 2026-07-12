# Fase 7 — Banco e schema de instalação limpa

Versão: **SupportPONTO v1.1.476**

## Objetivo

Reduzir o risco crítico identificado na auditoria: migrations históricas alteravam tabelas essenciais antes das migrations originais de criação serem alcançadas em uma instalação limpa.

## Correções aplicadas

- Criada migration base antecipada `2026-01-02-0001_CreateFreshInstallBaseSchema.php`.
- `employees` e `time_punches` passam a existir antes das migrations aditivas históricas.
- Migrations aditivas críticas passaram a validar `tableExists()` e `fieldExists()` antes de alterar schema.
- Migrations de criação de tabelas críticas passaram a ser idempotentes para evitar colisão em bancos já existentes.
- Índices de schedules/work_shifts agora só são criados se as tabelas existirem.
- Adicionada auditoria estática `tools/release/audit-clean-install-schema-static.php`.

## Escopo

Esta fase estabiliza o caminho de instalação limpa em nível estático e estrutural. A homologação definitiva ainda deve executar PostgreSQL real vazio com `php spark migrate --all` e seeders de produção.

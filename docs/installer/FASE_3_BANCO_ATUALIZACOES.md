# Fase 3 — Banco de dados e atualizações seguras

Versão: **SupportPONTO v1.1.476**

## Objetivo

Fortalecer o instalador para tratar atualização de versão como fluxo próprio, preservando dados, com backup SQL obrigatório, relatório de compatibilidade de migrations e validação pós-migration.

## Regras implantadas

1. `clean_install` continua destinado a banco novo/vazio.
2. `update` exige banco existente com tabelas em `public`.
3. `update` não executa `DROP SCHEMA`.
4. `update` não executa seeders iniciais.
5. `update` exige backup SQL real via `pg_dump` antes de executar migrations.
6. `destructive_reinstall` permanece isolado como fluxo destrutivo explícito.
7. Toda execução relevante grava eventos no journal do instalador.

## Novos artefatos técnicos

- `writable/installer/migration-compatibility-last.json`
- `writable/installer/schema-health-last.json`
- `writable/installer/backups/pre-reset-*.sql`
- `writable/installer/install-journal.json`

## Relatório de compatibilidade de migrations

Antes de migrar, o instalador agora gera um relatório com:

- quantidade de arquivos de migration locais;
- quantidade de migrations aplicadas no banco;
- amostra de migrations pendentes;
- tabelas críticas presentes;
- política do modo update.

No modo `update`, a execução é bloqueada se a tabela `migrations` não tiver histórico aplicável.

## Backup obrigatório em update

O modo `update` chama `pg_dump` antes de qualquer migration. Se o dump SQL real não for gerado, a atualização é bloqueada.

Manifesto JSON sem dump restaurável não é aceito no modo update.

## Validação pós-migration

Após as migrations, o instalador valida tabelas essenciais:

- `migrations`
- `employees`
- `time_punches`
- `audit_logs`
- `settings`

Também registra relatório de índices críticos esperados, incluindo:

- `idx_time_punches_employee_punch_time`
- `idx_audit_logs_created_at`
- `idx_employees_department_active_name`

Índices ausentes geram alerta técnico sem impedir a instalação, porque alguns nomes podem variar em bancos já migrados. Tabelas ausentes continuam bloqueantes.

## Resultado esperado

A atualização passa a ser conservadora: preserva dados existentes, exige backup restaurável, executa somente migrations e produz evidências técnicas para suporte.

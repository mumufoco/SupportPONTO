# Pacote 448 — Performance de consultas e índices

## Objetivo

Preparar o banco para uso real, reduzindo risco de lentidão em ponto eletrônico, relatórios e consultas administrativas.

## Problemas corrigidos

- Consultas críticas por período sem índices compostos suficientes.
- Relatório consolidado mensal com padrão N+1 para funcionários, atrasos e faltas.
- Relatório de justificativas com busca individual de funcionário dentro do loop.
- Falta de ferramenta segura para `EXPLAIN` quando PostgreSQL estiver disponível.

## Arquivos alterados/criados

- `app/Database/Migrations/2026-05-17-0448_PerformanceIndexesForReportsAndTimesheet.php`
- `app/Services/Reports/Concerns/ReportExecutionGeneratorsTrait.php`
- `app/Services/Database/QueryPlanAnalyzerService.php`
- `app/Config/Services.php`
- `docs/performance/CONSULTAS_INDICES_EXPLAIN_PACOTE_448.md`
- `tests/Feature/Package448PerformanceIndexesStaticTest.php`
- `public/version.json`

## Índices principais

- `time_punches`: funcionário, período, status, tipo e método.
- `timesheet_consolidated`: funcionário, período, recalculação, incompleto/justificado.
- `employees`: ativo, perfil, gestor, unidade, setor/departamento.
- `justifications`: funcionário, período, status.
- `schedules`: funcionário, período, status, setor/unidade.
- `audit_logs`: período, usuário e ação.
- `report_queue`: status, prioridade e criação.

## Compatibilidade

A migration é PostgreSQL-first e idempotente. Ela não remove dados, não altera colunas existentes e ignora índices cujas tabelas/colunas ainda não existam.

## Validação esperada

- Relatórios por período passam a consultar índices compostos.
- Relatório consolidado mensal não deve fazer uma consulta por funcionário.
- Consulta de justificativas não deve buscar funcionário individualmente dentro do loop.
- `QueryPlanAnalyzerService` deve permitir diagnóstico controlado de `EXPLAIN` para SELECT.

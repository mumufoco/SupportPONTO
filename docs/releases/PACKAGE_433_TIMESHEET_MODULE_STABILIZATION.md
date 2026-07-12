# Pacote 433 — Reestruturação do módulo de ponto eletrônico

## Objetivo

Estabilizar o núcleo do SupportPONTO: registro de ponto, sequência de marcações, justificativas, consolidação diária e integridade entre rotas, controllers, services, models, migrations e views do módulo de ponto eletrônico.

## Problemas tratados

- `TimesheetConsolidationListener` usava a coluna inexistente `work_date` em `timesheet_consolidated`.
- O evento `timePunchRegistered` estava configurado em `app/Config/Events.php`, mas não era disparado pelos serviços de registro de ponto.
- `PunchMethod::label()` não cobria métodos manuais, podendo gerar `UnhandledMatchError` em auditoria/recibo/listeners.
- A migration histórica `2026-02-01-0027_create_timesheet_consolidated_table.php` tentava recriar `timesheet_consolidated` e declarar FK para `justifications` antes da tabela existir.
- `TimesheetReadService` mapeava `saida` como início de intervalo e uma segunda `entrada` como fim de intervalo, ignorando os tipos canônicos `intervalo_inicio` e `intervalo_fim`.
- `TimesheetWorkflowService` tentava ler `punch_date`, coluna inexistente em `time_punches`.
- O módulo não tinha gate específico para impedir regressão em fluxo de ponto.

## Arquivos principais alterados/criados

- `app/Enums/PunchMethod.php`
- `app/Services/Timesheet/PunchService.php`
- `app/Services/Timesheet/ApiTimePunchService.php`
- `app/Services/Timesheet/TimesheetReadService.php`
- `app/Services/Timesheet/TimesheetWorkflowService.php`
- `app/Services/Timesheet/TimesheetDailyConsolidationService.php`
- `app/Listeners/TimesheetConsolidationListener.php`
- `app/Commands/TimesheetConsolidate.php`
- `app/Database/Migrations/2026-02-01-0027_create_timesheet_consolidated_table.php`
- `app/Database/Migrations/2026-05-12-0433_StabilizeTimesheetModuleSchema.php`
- `tools/release/audit-timesheet-module-integrity.php`
- `scripts/testing/timesheet-module-integrity-gate.sh`
- `tests/Feature/Package433TimesheetModuleIntegrityStaticTest.php`

## Consolidação diária

Foi criado `TimesheetDailyConsolidationService`, com dois contratos:

- `markDayForRecalculation()` — usado pelo listener após novo ponto.
- `consolidateDay()` — recalcula e grava `timesheet_consolidated`.

Também foi criado o comando:

```bash
php spark timesheet:consolidate --employee 1 --date 2026-05-12
php spark timesheet:consolidate --employee 1 --from 2026-05-01 --to 2026-05-31
php spark timesheet:consolidate --pending --limit 100
```

## Gate novo

Comandos adicionados:

```bash
composer run audit:timesheet
composer run test:timesheet-module-integrity
```

O gate verifica:

- rotas essenciais do módulo de ponto;
- models e campos obrigatórios;
- disparo do evento `timePunchRegistered`;
- listener sem coluna inválida `work_date`;
- service de consolidação diária;
- migration duplicada transformada em no-op de compatibilidade;
- migration 0433 com colunas/índices críticos;
- views essenciais;
- comando CLI de consolidação.

## Resultado esperado

O fluxo de ponto fica mais confiável:

1. colaborador registra ponto;
2. sistema grava `time_punches` com NSR/hash;
3. serviço dispara evento `timePunchRegistered`;
4. listener marca o dia como pendente de recálculo;
5. comando/job consolida o dia em `timesheet_consolidated`;
6. telas de espelho, histórico e banco de horas leem dados consistentes.

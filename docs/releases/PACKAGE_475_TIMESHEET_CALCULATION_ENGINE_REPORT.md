# Package 475 — Motor canônico de cálculo de jornada

Versão: **SupportPONTO v1.1.476**

## Correções aplicadas

- Criado motor canônico de cálculo de jornada.
- Criadas policies para pareamento, jornada esperada, tolerância, extras/débitos, intervalo mínimo e período noturno.
- `TimesheetComputationService` passou a ser wrapper do motor canônico.
- `TimesheetDailyConsolidationService` passou a usar os campos calculados pelo motor.
- `TimePunchModel::calculateHours()` deixou de usar cálculo simplificado manual.
- Criada auditoria estática do motor de cálculo.
- Criado teste estático da Fase 10.

## Validação esperada

- `php -l` dos arquivos alterados.
- `php tests/Feature/TimesheetPhase10CalculationEngineStaticTest.php`.
- `php tools/release/audit-timesheet-calculation-engine-static.php`.
- `php tools/release/audit-version-consistency.php`.
- `unzip -t` do pacote final.

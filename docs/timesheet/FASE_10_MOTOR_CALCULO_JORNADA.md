# Fase 10 — Motor canônico de cálculo de jornada

Versão: **SupportPONTO v1.1.476**

## Objetivo

Substituir cálculos simplificados e dispersos por um motor canônico de cálculo de jornada, preparado para múltiplos intervalos, tolerância diária, intervalo mínimo, débito, extra, jornada incompleta e horas em período noturno.

## Arquivos principais

- `app/Services/Timesheet/Calculation/TimesheetCalculationEngine.php`
- `app/Services/Timesheet/Calculation/WorkPairingPolicy.php`
- `app/Services/Timesheet/Calculation/JornadaPolicy.php`
- `app/Services/Timesheet/Calculation/IntervalPolicy.php`
- `app/Services/Timesheet/Calculation/TolerancePolicy.php`
- `app/Services/Timesheet/Calculation/OvertimePolicy.php`
- `app/Services/Timesheet/Calculation/NightShiftPolicy.php`
- `app/Services/Timesheet/TimesheetComputationService.php`
- `app/Services/Timesheet/TimesheetDailyConsolidationService.php`
- `app/Models/TimePunchModel.php`

## Resultado técnico

- Cálculo diário centralizado em `TimesheetCalculationEngine`.
- Pareamento de `entrada/saida` e `intervalo_inicio/intervalo_fim` centralizado em policy própria.
- Cálculo de horas extras e débito com tolerância diária configurável por contexto.
- Cálculo de violação de intervalo mínimo.
- Cálculo de horas noturnas por sobreposição de períodos entre 22:00 e 05:00.
- Consolidação diária passou a consumir os resultados do motor.
- `TimePunchModel::calculateHours()` deixou de recalcular pares manualmente.

## Observação

A Fase 10 não altera schema de banco. Ela cria a base de cálculo canônica e mantém compatibilidade com chaves antigas esperadas por relatórios existentes.

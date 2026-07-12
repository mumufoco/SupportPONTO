# Package 474 — Fase 9: Serviço Canônico de Marcação

Versão: **SupportPONTO v1.1.476**

## Problema tratado

Antes desta fase, web, API, QR Code e aprovação de pendências podiam inserir registros de ponto por caminhos diferentes. Isso aumentava o risco de divergência de regras, auditoria incompleta e manutenção difícil.

## Correções aplicadas

- Criado command/DTO único para solicitação de ponto.
- Criado serviço canônico `TimesheetPunchRegistrationService`.
- Fluxos críticos passaram a convergir para o serviço canônico.
- Inserções diretas em `TimePunchModel::insertWithIntegrityLock()` foram removidas dos fluxos de entrada.
- Auditoria e evento de ponto registrado foram centralizados.
- Criado teste e auditoria estática da Fase 9.

## Arquivos principais

- `app/DTO/Timesheet/PunchRegistrationCommand.php`
- `app/Services/Timesheet/TimesheetPunchRegistrationService.php`
- `app/Services/Timesheet/PunchService.php`
- `app/Services/Timesheet/ApiTimePunchService.php`
- `app/Controllers/QRCode/QRCodeController.php`
- `app/Services/Timesheet/PendingPunchService.php`
- `tools/release/audit-canonical-time-punch-registration-static.php`
- `tests/Feature/TimesheetPhase9CanonicalRegistrationStaticTest.php`

## Resultado esperado

Todos os canais de marcação relevantes passam a usar uma única regra de negócio para sequência, cooldown, geofence, integridade, NSR, auditoria e payload.

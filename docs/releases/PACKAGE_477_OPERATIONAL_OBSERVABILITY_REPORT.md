# Package 478 — Fase 12: Observabilidade operacional do sistema inteiro

## Resumo

Este pacote adiciona observabilidade operacional fora do instalador, com telemetria NDJSON, alertas locais, health checks ampliados, headers de correlação e geração de bundle de suporte sanitizado da aplicação.

## Arquivos principais

- `app/Services/Observability/OperationalTelemetryService.php`
- `app/Services/Observability/OperationalAlertService.php`
- `app/Services/Support/ApplicationSupportBundleService.php`
- `app/Commands/ApplicationSupportBundle.php`
- `app/Services/Health/SystemHealthCheckService.php`
- `app/Traits/ObservabilityTrait.php`
- `app/Filters/CorrelationIdFilter.php`
- `app/Config/Routes/20_user_area.php`
- `tools/release/audit-application-observability-static.php`
- `tests/Feature/TimesheetPhase12OperationalObservabilityStaticTest.php`

## Resultado esperado

- Suporte técnico consegue gerar pacote de diagnóstico sem expor segredos.
- Operação consegue correlacionar request, logs, health e auditoria por `request_id`/`correlation_id`.
- Health detalhado cobre banco, fila, storage, logs, DeepFace, websocket, migrations e observabilidade.
- Eventos operacionais ficam disponíveis para análise local.

## Validação

Executar:

```bash
php tests/Feature/TimesheetPhase12OperationalObservabilityStaticTest.php
php tools/release/audit-application-observability-static.php
php tools/release/audit-version-consistency.php
```

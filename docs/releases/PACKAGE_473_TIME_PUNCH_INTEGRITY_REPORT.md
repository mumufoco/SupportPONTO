# Package 473 — Integridade forte de ponto eletrônico

Versão: **SupportPONTO v1.1.476**

## Entregas

- `app/Services/Timesheet/NsrGeneratorService.php`
- `app/Services/Timesheet/TimePunchConcurrencyService.php`
- `app/Services/Timesheet/TimePunchIntegrityChainService.php`
- `app/Database/Migrations/2026-05-18-0473_StrengthenTimePunchIntegrity.php`
- `tests/Feature/TimesheetPhase8IntegrityStaticTest.php`
- `tools/release/audit-time-punch-integrity-static.php`

## Correções de risco alto

1. Removido fallback `SELECT MAX(nsr)`.
2. Desativada geração NSR por trigger/sequence legado.
3. Adicionado lock transacional por colaborador.
4. Adicionado HMAC encadeado para registros.
5. Bloqueada biometria fingerprint por comparação textual.

## Validação recomendada em produção/homologação

```bash
php spark migrate --all
php tools/release/audit-time-punch-integrity-static.php
```

Além da validação estática, executar teste concorrente real com múltiplas requisições simultâneas para o mesmo colaborador.

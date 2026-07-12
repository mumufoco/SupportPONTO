# Fase 9 — Serviço Canônico de Marcação de Ponto

Versão: **SupportPONTO v1.1.476**

## Objetivo

Eliminar duplicação de regras entre web, API, QR Code, kiosk, biometria facial e aprovações de ponto pendente.

## Entregas

- Criado `App\DTO\Timesheet\PunchRegistrationCommand` como contrato único de entrada.
- Criado `App\Services\Timesheet\TimesheetPunchRegistrationService` como fluxo canônico.
- `PunchService::registerPunch()` passa a delegar ao serviço canônico.
- `ApiTimePunchService::registerPunch()` passa a delegar ao serviço canônico.
- `QRCodeController::validateQRCode()` deixa de inserir diretamente em `TimePunchModel`.
- `PendingPunchService::approve()` efetiva ponto aprovado pelo mesmo fluxo canônico.

## Regras centralizadas

- normalização e validação do tipo de marcação;
- validação do método;
- cooldown;
- sequência esperada;
- geolocalização e geofence;
- validação facial quando aplicável;
- gravação por `insertWithIntegrityLock()`;
- NSR/HMAC/cadeia de integridade;
- auditoria;
- evento `timePunchRegistered`;
- payload de retorno padronizado.

## Observação

Aprovação de ponto pendente usa flags explícitas para preservar a decisão gerencial já auditada:

- `skipSequenceValidation: true`
- `skipCooldownValidation: true`
- `requireGeofenceConfirmation: false`

Isso evita que uma aprovação retroativa seja bloqueada por regras operacionais do momento da aprovação, mantendo a efetivação dentro do fluxo de integridade.

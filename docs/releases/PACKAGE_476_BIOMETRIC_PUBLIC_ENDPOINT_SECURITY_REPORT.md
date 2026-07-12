# Package 476 — Segurança biométrica e endpoints públicos

Versão: **SupportPONTO v1.1.476**

## Escopo

Fase 11 do roadmap: endurecimento de biometria e rotas públicas de terminal/kiosk.

## Arquivos principais

- `app/Services/Terminal/PublicTerminalSecurityService.php`
- `app/Filters/PublicTerminalSecurityFilter.php`
- `app/Models/PunchTerminalDeviceModel.php`
- `app/Models/PunchTerminalNonceModel.php`
- `app/Database/Migrations/2026-05-18-0476_HardenBiometricTerminalSecurity.php`
- `app/Services/Timesheet/PunchService.php`
- `app/Services/Timesheet/TimePunchFlowService.php`
- `app/Services/Timesheet/TimePunchEndpointService.php`
- `app/Services/Timesheet/Endpoint/TimePunchEndpointFaceGuard.php`
- `app/Config/Filters.php`

## Resultado

- Terminal público deixa de depender apenas de IP.
- Token v2 passa a ter terminal, fingerprint, TTL curto, nonce e assinatura.
- Replay é bloqueado por persistência de nonce.
- Rate limit por terminal/IP foi adicionado.
- Fingerprint passa a usar engine biométrica SourceAFIS.
- Comparação insegura textual continua removida.
- CSRF continua desabilitado para terminais públicos, mas compensado por autenticação de terminal e anti-replay.

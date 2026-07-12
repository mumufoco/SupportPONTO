# Fase 11 — Segurança biométrica e endpoints públicos

Versão: **SupportPONTO v1.1.476**

## Objetivo

Endurecer os fluxos públicos de terminal/kiosk e biometria, reduzindo riscos de replay, token legado, fingerprint frágil, abuso por IP compartilhado e falta de rastreabilidade por dispositivo.

## Alterações principais

- Criada tabela `punch_terminal_devices` para registrar terminais autorizados.
- Criada tabela `punch_terminal_nonces` para bloquear reuso de token.
- Criado serviço `App\Services\Terminal\PublicTerminalSecurityService`.
- Criado filtro `terminal-security` para endpoints públicos de terminal.
- Tokens de terminal passam a usar formato `v2.timestamp.terminal_id.device_fingerprint.nonce.signature`.
- Token v2 tem TTL curto e assinatura HMAC derivada do segredo do terminal.
- Nonce é persistido e não pode ser reutilizado.
- Wildcard de IP `*` fica proibido em produção no fluxo endurecido.
- `kiosk/token` passa a exigir `X-Terminal-Id` e `X-Terminal-Secret` ou equivalentes por formulário.
- Endpoints de punch terminal exigem `X-Kiosk-Token`/`kiosk_token` v2.
- `TimePunchEndpointFaceGuard` mantém HTTPS e throttle facial, mas não consome novamente o nonce.
- Fingerprint deixa de retornar sempre bloqueio e passa a usar `FingerprintMatchingService` + `SourceAFISService`.
- Comparação textual/heurística segue proibida.

## Como cadastrar terminal

Registrar linha em `punch_terminal_devices` com:

- `terminal_id`: identificador estável do terminal;
- `secret_hash`: `password_hash()` de um segredo forte;
- `allowed_ip`: IPs permitidos separados por vírgula, opcional;
- `active`: `true`.

Exemplo conceitual:

```php
password_hash('segredo-forte-do-terminal', PASSWORD_DEFAULT);
```

## Cabeçalhos exigidos

Para gerar token:

```text
X-Terminal-Id: terminal-almoxarifado-01
X-Terminal-Secret: segredo-forte-do-terminal
X-Device-Fingerprint: fingerprint-estavel-do-dispositivo
```

Para registrar ponto no terminal:

```text
X-Terminal-Id: terminal-almoxarifado-01
X-Device-Fingerprint: fingerprint-estavel-do-dispositivo
X-Kiosk-Token: v2....
```

## Política legado

Tokens antigos só são aceitos se `ALLOW_LEGACY_KIOSK_TOKEN=true` e o ambiente não for `production`.

Em produção, token legado fica bloqueado.

# Fase 8 — Integridade forte do ponto eletrônico

Versão: **SupportPONTO v1.1.476**

## Objetivo

Blindar a emissão de registros de ponto contra inconsistência de NSR, concorrência, alteração silenciosa e biometria frágil.

## Alterações principais

- Criação de `NsrGeneratorService` como fonte única canônica de NSR.
- Remoção do fallback `SELECT MAX(nsr)` em produção.
- Desativação da geração legada por trigger/sequence PostgreSQL.
- Criação de `TimePunchConcurrencyService` com `pg_advisory_xact_lock` por colaborador.
- Criação de `TimePunchIntegrityChainService` com hash público + HMAC encadeado.
- Inclusão de colunas de cadeia de integridade em `time_punches`.
- Registro de ponto via web/API/QR/aprovação passa a usar `insertWithIntegrityLock()`.
- Remoção do algoritmo textual inseguro para fingerprint.

## Campos adicionados a `time_punches`

- `previous_hash`
- `chain_hash`
- `hash_algorithm`
- `hash_version`
- `integrity_key_id`
- `integrity_signed_at`

## Variável recomendada

Definir em produção:

```env
TIME_PUNCH_INTEGRITY_KEY=base64-ou-hex-seguro-fora-do-banco
```

Se ausente, o sistema usa `APP_KEY`. Se ambas estiverem ausentes, o registro de ponto é bloqueado por segurança.

## Resultado esperado

- NSR passa a ser gerado por um único mecanismo atômico.
- Requisições simultâneas por colaborador deixam de passar pela mesma janela de validação.
- Cada registro recebe envelope de integridade com HMAC e vínculo ao hash anterior do mesmo colaborador.
- Fingerprint textual deixa de ser aceito como autenticação biométrica produtiva.

# Pacote 435 — Auditoria, logs e rastreabilidade

## Objetivo

Estabilizar a trilha de auditoria corporativa do SupportPONTO para evitar trilhas paralelas, perda de contexto e regressões em eventos críticos.

## Correções estruturais

- A migration legada `FixAuditTableStructure` não cria mais a tabela paralela `audit` em instalações novas.
- A migration `AddUpdatedAtToAuditTable` virou compatibilidade segura e não quebra quando a tabela legada `audit` não existe.
- A migration `2026-05-12-0435_StabilizeAuditLoggingSchema.php` garante colunas de rastreabilidade em `audit_logs`:
  - `actor_type`
  - `request_id`
  - `source`
  - `context_data`
  - `url`
  - `method`
  - `row_checksum`
- A trilha `audit_logs` permanece como contrato canônico.

## Correções funcionais

- `AuditModel::log()` agora preenche contexto HTTP/CLI, método, URL, origem, tipo de ator e request id.
- O checksum encadeado passa a considerar os campos de rastreabilidade novos.
- `AuditTrailService` centraliza gravação de eventos críticos.
- `AuditEventCatalog` padroniza a taxonomia de ações auditáveis.
- Exportação CSV de auditoria passa a gerar evento `AUDIT_EXPORTED`.

## Gate novo

Arquivos:

```text
tools/release/audit-audit-log-integrity.php
scripts/testing/audit-log-integrity-gate.sh
tests/Feature/Package435AuditLogIntegrityStaticTest.php
```

Comandos:

```bash
composer run audit:audit-logs
composer run test:audit-log-integrity
```

O gate bloqueia:

- ausência de arquivos centrais de auditoria;
- `AuditModel` sem contrato de rastreabilidade;
- checksum sem campos críticos;
- criação da tabela legada `audit`;
- ausência do catálogo de eventos críticos;
- rotas de auditoria sem filtros de segurança;
- scripts de release sem execução do gate.

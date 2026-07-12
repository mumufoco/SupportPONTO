# Fase 12 — Observabilidade operacional do sistema inteiro

## Objetivo

Expandir o padrão de diagnóstico criado no instalador para a aplicação SupportPONTO em execução, cobrindo logs estruturados, correlação de requisições, health checks internos, bundle de suporte sanitizado e alertas operacionais locais.

## Implementações

- `OperationalTelemetryService`: grava eventos operacionais em `writable/observability/operational-events-YYYY-MM-DD.ndjson`.
- `OperationalAlertService`: grava alertas críticos em `writable/observability/operational-alerts-YYYY-MM-DD.ndjson`.
- `ApplicationSupportBundleService`: gera bundle sanitizado em `writable/support-bundles`.
- Comando CLI `php spark support:bundle`.
- Endpoint administrativo `admin/health/support-bundle` protegido por `auth` + `admin`.
- `SystemHealthCheckService` agora cobre storage, observabilidade e websocket além de banco, fila, DeepFace, logs, migrations e ambiente.
- Respostas HTTP passam a propagar `X-Request-ID` e `X-Correlation-ID`.

## Bundle de suporte

O bundle inclui:

- `manifest.json`
- `health-detailed.json`
- `release.json`
- `public-version.json`
- `environment-summary.json`
- `latest-app-log-tail.txt`
- `operational-events-tail.ndjson`
- artefatos recentes do instalador quando existirem

Todos os dados passam por `SensitiveDataSanitizer` para reduzir risco de vazamento de senhas, tokens, caminhos internos, biometria, imagens, IPs, CPF/CNPJ e segredos.

## Comandos úteis

```bash
php spark support:bundle
php spark support:bundle --json
php spark support:bundle --cleanup-observability
php tools/release/audit-application-observability-static.php
```

## Variáveis operacionais

```env
OBSERVABILITY_RETENTION_DAYS=30
WEBSOCKET_ENABLED=false
WEBSOCKET_PORT=8080
WEBSOCKET_HEALTH_HOST=127.0.0.1
```

## Segurança

- O bundle não deve ser publicado em pasta pública.
- O endpoint web exige autenticação de administrador.
- O JSON de health detalhado continua restrito.
- Logs de biometria, imagens, tokens e credenciais são sanitizados.

## Limitação

Esta fase adiciona observabilidade local e suporte técnico. Integração com Prometheus, Sentry, Grafana, ELK/OpenSearch ou serviços externos deve ser feita em pacote posterior para não criar dependências obrigatórias de produção.

# Saúde, logs e monitoramento — Pacote 450

## Objetivo

Criar observabilidade real para operação do SupportPONTO em produção, sem depender de tela quebrada ou log bruto para descobrir falhas.

## Endpoints

### Público/sanitizado

- `GET /healthz`: liveness simples. Deve responder se o processo web está vivo.
- `GET /healthz/ready`: readiness para balanceador/proxy. Valida banco, writable, env, migrations e fila em modo sanitizado.
- `GET /health`: compatibilidade com endpoint antigo.
- `GET /health/readiness`: compatibilidade com readiness antigo.

### Sensível/protegido

- `GET /healthz/detailed`
- `GET /health/detailed`

Esses endpoints exigem uma das condições:

1. usuário autenticado com perfil `admin`; ou
2. header `X-Health-Token` igual a `HEALTH_DETAILS_TOKEN` configurado no ambiente ou nas configurações internas.

Nunca exponha `HEALTH_DETAILS_TOKEN` em repositório, painel público, log, print de tela ou documentação externa.

## Tela administrativa

- `GET /admin/health`
- `GET /admin/system-health` permanece como alias legado.
- `GET /admin/health/json` retorna o diagnóstico detalhado para administrador autenticado.

A tela verifica:

- banco de dados;
- diretórios `writable`;
- migrations;
- versão instalada;
- fila `async_jobs`;
- DeepFace e circuit breaker;
- logs recentes;
- ambiente PHP/.env.

## Recomendações para Cloudflare/aaPanel

- Use `/healthz` para checagem leve de uptime.
- Use `/healthz/ready` para readiness de tráfego real.
- Não configure monitor externo público para `/healthz/detailed`.
- Se precisar integrar monitor interno, envie `X-Health-Token` por canal seguro.

## Worker e fila

Quando o painel indicar jobs presos ou falhas:

```bash
php spark jobs:process --daemon --queues=reports,biometric,exports,notifications,maintenance,default
php spark jobs:cleanup
```

Em produção, prefira `systemd`, Supervisor ou cron controlado conforme documentação do Pacote 449.

## Logs

O diagnóstico lê somente metadados e contagens recentes de `writable/logs`, sem mostrar stacktrace ou dados sensíveis na tela pública. Para investigação profunda, acesse o servidor com permissão apropriada.

# Pacote 450 — Logs, monitoramento e saúde do sistema

## Objetivo

Criar observabilidade real para banco, permissões, migrations, versão, filas, DeepFace e logs, com tela administrativa e endpoints seguros.

## Entregas

- Serviço canônico `App\Services\Health\SystemHealthCheckService`.
- Compatibilidade preservada em `App\Services\Monitoring\SystemObservabilityService`.
- Controller administrativo `App\Controllers\Admin\HealthController`.
- Alias legado `Admin\SystemHealthController` preservado.
- Controller público/interno `HealthController` reescrito para liveness/readiness/detailed.
- Tela administrativa nova em `app/Views/admin/health/index.php`.
- Alias legado `app/Views/admin/system_health.php` preservado.
- Endpoint `/healthz` criado.
- Endpoint `/healthz/ready` criado.
- Endpoint `/healthz/detailed` protegido por admin ou `X-Health-Token`.

## Checks implementados

- Banco: conexão e `SELECT 1`.
- Writable: `cache`, `logs`, `session`, `uploads`, `exports`, `installer`.
- Migrations: arquivos vs tabela `migrations` quando disponível.
- Versão: leitura de `public/version.json` e helper `app_version`.
- Fila: tabela `async_jobs`, contagem por status e jobs presos.
- DeepFace: health check e estado do circuit breaker.
- Logs: existência, permissão e contagem recente de erros/warnings/criticals.
- Ambiente: extensões PHP, `.env`, timezone e baseURL.

## Segurança

- `/healthz` e `/healthz/ready` não expõem dados sensíveis.
- `/healthz/detailed` e `/health/detailed` exigem administrador autenticado ou token interno.
- Metadados passam por sanitização recursiva de chaves sensíveis.

## Validação esperada

- Admin consegue diagnosticar o sistema pela interface.
- Endpoint leve responde sem exigir sessão.
- Endpoint detalhado bloqueia acesso não autorizado.
- Falhas de banco/DeepFace/fila/logs viram alertas, não fatal error.

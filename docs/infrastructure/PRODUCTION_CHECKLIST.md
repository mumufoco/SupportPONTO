# Production Checklist

Versão alvo: **v1.1.498**

## Pré-condições mínimas
- ambiente de produção definido com HTTPS
- segredos válidos configurados
- banco e storage testados
- release package validado
- `artifact-manifest.json` marcado como `release-package` no artefato final de produção
- smoke test operacional executado

## Verificações objetivas
- `bash scripts/testing/release-audit.sh`
- `php spark release:gate --save --no-connections`
- `bash scripts/health-check.sh`

## Endurecimento de URL / Proxy
- `app.baseURL` explícito e sem placeholder
- `app.allowedHostnames` explícito e sem `CHANGE-ME.example.com`
- `app.proxyIPs` vazio somente quando o PHP termina TLS diretamente
- em proxy reverso, `app.proxyIPs` configurado com IPs ou CIDRs confiáveis
- `app.forceGlobalSecureRequests=true` e `app.enforceHttpsSchemeInBaseURL=true` em produção
- `app.cookieSecure=true` e cookies/sessão conferidos após subir atrás do proxy real
- `session.matchIP` revisado conforme a topologia real; manter `false` por padrão em proxies reversos, CGNAT e redes móveis

- Validar o fluxo de confirmação reforçada de senha (step-up) nas ações críticas administrativas e de conta antes do go-live.

- `PROCESS_ALLOW_WEB_SHELL=false`, `BACKUP_ALLOW_WEB_RUNTIME=false` e `INSTALLER_ALLOW_WEB_AUTOMATION=false` mantidos por padrão em produção
- jobs que precisem de shell/processo externo processados por worker CLI (`php spark jobs:process`)
- Resets administrativos de configurações agora criam snapshot preventivo em `writable/settings-snapshots/` antes da alteração sempre que o grupo for exportável.
- Reset global de configurações via interface web permanece bloqueado; para cenários extraordinários, use rotina controlada em CLI.


## Fechamento obrigatório antes do go-live

- executar `bash scripts/testing/go-live-gate.sh`
- executar `bash scripts/testing/final-production-readiness.sh`
- anexar os relatórios gerados em `build/go-live-gate/` e `build/final-production-readiness/`

# Production Readiness

Versão alvo: **v1.1.498**

A release só é considerada pronta quando:
- o artefato correto é um `release-package`
- o manifesto do artefato está coerente
- o gate operacional não aponta bloqueadores críticos
- os checks de instalação, suporte e biometria foram revisados
- o checklist de produção foi executado


Observação operacional: baseURL, allowedHostnames e proxyIPs devem refletir a topologia real do ambiente. Em produção, o runtime passa a evitar `HTTP_HOST` como fonte de verdade quando `app.baseURL` não estiver explícito, reduzindo risco de geração de URL baseada em host não confiável.

Política de sessão: `session.matchIP` deve ser decisão explícita do ambiente. Não o trate como hardening automático. Atrás de proxies, CGNAT ou redes móveis, mantê-lo ativo pode derrubar sessões legítimas.

- Validar o fluxo de confirmação reforçada de senha (step-up) nas ações críticas administrativas e de conta antes do go-live.

- `PROCESS_ALLOW_WEB_SHELL=false`, `BACKUP_ALLOW_WEB_RUNTIME=false` e `INSTALLER_ALLOW_WEB_AUTOMATION=false` mantidos por padrão em produção
- jobs que precisem de shell/processo externo processados por worker CLI (`php spark jobs:process`)
- Resets administrativos de configurações agora criam snapshot preventivo em `writable/settings-snapshots/` antes da alteração sempre que o grupo for exportável.
- Reset global de configurações via interface web permanece bloqueado; para cenários extraordinários, use rotina controlada em CLI.


## Gate final recomendado

1. `bash scripts/testing/go-live-gate.sh`
2. `bash scripts/testing/final-production-readiness.sh`

No source-package, a validação permanece documental/estática. Em runtime completo, os checks de `php spark` e smoke opcional passam a ser executados.

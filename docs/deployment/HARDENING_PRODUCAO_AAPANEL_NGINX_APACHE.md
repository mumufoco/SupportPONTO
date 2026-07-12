# Hardening de produção — aaPanel, Apache/Nginx e Cloudflare

## Objetivo

Este checklist separa instalação de operação em produção. Em produção o SupportPONTO deve iniciar com `CI_ENVIRONMENT=production`, debug desativado, instalador web bloqueado, diretórios internos protegidos e `writable/` inacessível por HTTP.

## .env mínimo de produção

```env
APP_ENV = production
CI_ENVIRONMENT = production
APP_DEBUG = false
CI_DEBUG = false
KINT_ENABLED = false
ALLOW_WEB_INSTALLER = false
INSTALLER_PRODUCTION_WEB_OVERRIDE = false
PROCESS_ALLOW_WEB_SHELL = false
BACKUP_ALLOW_WEB_RUNTIME = false
SECURITY_PRODUCTION_HARDENING = true
logger.threshold = 4
```

## Instalador

Após instalar, mantenha `writable/installer/installed.lock`. O acesso web ao instalador é bloqueado quando esse lock existe. Para uma instalação inicial via web, libere temporariamente e apenas durante a janela controlada:

```env
ALLOW_WEB_INSTALLER = true
INSTALLER_PRODUCTION_WEB_OVERRIDE = true
INSTALLER_TOKEN = token-longo-aleatorio
INSTALLER_ALLOWED_IPS = 203.0.113.10
```

Depois de finalizar, volte `ALLOW_WEB_INSTALLER=false`, remova o override e preserve o lock. Para manutenção, prefira o CLI.

## Apache/aaPanel

- Aponte o vhost para `public/`.
- Mantenha `Options -Indexes`.
- Bloqueie acesso a `.env`, `writable/`, `vendor/`, `tools/`, `tests/`, `build/`, `docs/`, `release.json` e `artifact-manifest.json`.
- Não habilite `display_errors`.
- Ative HSTS apenas após HTTPS/Cloudflare estar confirmado.

## Nginx/aaPanel

Exemplo seguro para blocos sensíveis:

```nginx
location ~ /\. { deny all; }
location ~ ^/(app|system|writable|vendor|tests|tools|build|storage|docker|docs|deepface-api)(/|$) { deny all; }
location ~* /(composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml|spark|release\.json|artifact-manifest\.json|openapi\.yaml)$ { deny all; }
location / { try_files $uri $uri/ /index.php?$query_string; }
```

## Validação

Execute antes do deploy final:

```bash
php tools/quality/production-hardening-audit.php
bash scripts/testing/production-hardening-gate.sh
bash scripts/testing/essential-regression-gate.sh
```

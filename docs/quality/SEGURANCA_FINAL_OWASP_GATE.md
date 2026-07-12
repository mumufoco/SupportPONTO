# Gate final de segurança OWASP — Pacote 458

O gate final de segurança valida, sem depender de `vendor`, os controles corporativos mínimos para release.

## Comando direto

```bash
php tools/quality/security-final-audit.php
```

## Wrapper

```bash
bash scripts/testing/security-final-gate.sh
```

## Composer

```bash
composer test:security-final
composer test:release-critical
```

## O que é validado

- RBAC e filtros `role`/`api-role`.
- Proteção de APIs com OAuth2/RBAC/JSON validation.
- Senha inicial sem persistência em texto puro.
- Argon2id e troca obrigatória no primeiro login.
- CSRF, sanitização e CSP.
- Headers HTTP seguros.
- Instalador com `installed.lock`, dry-run, diagnóstico e confirmação destrutiva.
- Upload seguro fora de `public` quando sensível.
- Health checks, logs e auditoria.
- Circuit breaker do DeepFace.
- LGPD, exportação redigida e retenção.
- Relatório final OWASP e checklist corporativo.

## Resultado esperado

O comando deve encerrar com código `0`. Qualquer falha deve bloquear o release até correção ou justificativa formal documentada.

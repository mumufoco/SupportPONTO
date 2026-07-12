# Checklist de segurança corporativa — Pacote 458

## Ambiente

- [ ] `CI_ENVIRONMENT = production` em produção.
- [ ] `APP_ENV = production` em produção.
- [ ] `APP_DEBUG = false`.
- [ ] `CI_DEBUG = false`.
- [ ] `.env` fora de versionamento e inacessível via HTTP.
- [ ] `writable/`, `vendor/`, `tools/`, `tests/`, `build/` e `docs/` inacessíveis via HTTP.
- [ ] Permissões de arquivos revisadas para usuário/grupo do servidor web.

## Instalador

- [ ] `writable/installer/installed.lock` existe após instalação.
- [ ] Instalador web bloqueado em produção.
- [ ] `ALLOW_WEB_INSTALLER=false` após instalação.
- [ ] `INSTALLER_TOKEN` removido ou rotacionado após uso.
- [ ] Reset destrutivo somente por CLI com `--force-reset` e confirmação forte.
- [ ] Backup executado antes de qualquer operação destrutiva.

## Autenticação

- [ ] Admin inicial trocou senha no primeiro login.
- [ ] Senhas com política mínima aplicada.
- [ ] Hash Argon2id ativo.
- [ ] 2FA habilitado para administradores, RH, DPO e auditoria quando exigido pela política interna.
- [ ] Sessões expiram conforme política corporativa.

## Autorização

- [ ] Perfil `funcionario` não acessa administração.
- [ ] Perfil `funcionario` não acessa dados de outro funcionário sem escopo.
- [ ] Perfil `gestor` limitado à equipe/departamento autorizado.
- [ ] Perfil `auditor` limitado a leitura/auditoria.
- [ ] Perfil `dpo` limitado a LGPD/privacidade.
- [ ] Rotas API protegidas por OAuth2 e RBAC quando sensíveis.

## CSRF, XSS e saída segura

- [ ] Formulários web usam CSRF.
- [ ] Campos textuais passam por sanitização quando necessário.
- [ ] Views usam escape por padrão.
- [ ] Mensagens flash não renderizam HTML não confiável.
- [ ] Scripts inline novos são evitados ou recebem nonce/CSP.

## Headers e navegador

- [ ] CSP ativa em modo compatível.
- [ ] `X-Frame-Options` ativo.
- [ ] `X-Content-Type-Options` ativo.
- [ ] `Referrer-Policy` ativo.
- [ ] `Permissions-Policy` ativo.
- [ ] HSTS ativado somente após HTTPS/Cloudflare/aaPanel validados.

## Uploads

- [ ] Uploads sensíveis ficam em `writable/uploads`.
- [ ] `public/uploads` não executa scripts.
- [ ] Extensões PHP, HTML, SVG, JS e executáveis bloqueadas.
- [ ] MIME real validado.
- [ ] Nome final aleatório.
- [ ] Download privado passa por controller autenticado.

## Banco e dados sensíveis

- [ ] PostgreSQL acessível somente por rede autorizada.
- [ ] Usuário do banco com privilégio mínimo possível.
- [ ] Backups criptografados quando armazenados fora do servidor.
- [ ] Dados biométricos protegidos e não expostos em exportações comuns.
- [ ] Solicitações LGPD rastreadas.
- [ ] Retenção configurada conforme política da empresa.

## Logs, saúde e monitoramento

- [ ] `/healthz` retorna apenas estado mínimo.
- [ ] `/healthz/detailed` protegido por admin ou token.
- [ ] Logs de erro revisados periodicamente.
- [ ] Logs de ações destrutivas do instalador preservados.
- [ ] Fila `async_jobs` monitorada.
- [ ] DeepFace/circuit breaker monitorado.

## Dependências

- [ ] `composer install --no-dev --optimize-autoloader` executado em produção.
- [ ] `composer audit` executado quando disponível.
- [ ] Extensões PHP validadas pelo script de provisionamento.
- [ ] PostgreSQL client (`psql`, `pg_dump`) disponível para diagnóstico/backup.

## Gates finais

- [ ] `php tools/quality/security-final-audit.php` aprovado.
- [ ] `bash scripts/testing/security-final-gate.sh` aprovado.
- [ ] `bash scripts/testing/essential-regression-gate.sh` aprovado.
- [ ] `php tools/quality/production-hardening-audit.php` aprovado.
- [ ] `php tools/quality/documentation-audit.php` aprovado.

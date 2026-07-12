# Configuração `.env` para produção — SupportPONTO

## Regras obrigatórias

1. `CI_ENVIRONMENT` e `APP_ENV` devem ser `production`.
2. `app.appTimezone` deve ser `America/Sao_Paulo`.
3. `app.baseURL` deve apontar para a URL final do domínio, com barra final.
4. PostgreSQL é o driver suportado em produção: `database.default.DBDriver = Postgre`.
5. A senha inicial administrativa não deve ser gravada no `.env`.
6. Segredos precisam ser gerados no servidor e nunca copiados de exemplo.

## Variáveis críticas

- `encryption.key`
- `JWT_SECRET_KEY`
- `QR_SECRET_KEY`
- `DEEPFACE_API_KEY`
- `database.default.*`
- `PG*`
- `ADMIN_INITIAL_EMAIL`
- `ADMIN_INITIAL_CPF`

## Geradores úteis

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
php -r "echo bin2hex(random_bytes(24)) . PHP_EOL;"
```

## Validação pelo instalador

```bash
php tools/installer/install_cli.php --diagnose
php tools/installer/install_cli.php --dry-run \
  --app-url=https://ponto.supportsondagens.com.br/ \
  --db-host=127.0.0.1 \
  --db-name=supportponto \
  --db-user=supportponto \
  --db-pass='SENHA_FORTE' \
  --admin-name='Administrador' \
  --admin-email=contato@supportsondagens.com.br \
  --admin-cpf=00000000000
```

## Cloudflare/aaPanel

- Configure `app.proxyIPs` somente com proxies confiáveis.
- Ative `SECURITY_HSTS_ENABLED=true` somente após validar HTTPS fim a fim.
- Mantenha `CSP_REPORT_ONLY=true` durante homologação se ainda houver telas com inline legado.

# Requisitos de ambiente — SupportPONTO

Este documento define o mínimo para instalar e operar o SupportPONTO em produção sem tentativa e erro.

## Plataforma recomendada

- Ubuntu Server 24.04 LTS ou 22.04 LTS.
- aaPanel com PHP 8.3 do domínio.
- PostgreSQL como banco oficial.
- Nginx ou Apache, com o domínio apontando preferencialmente para `public/`.
- HTTPS ativo, preferencialmente com Cloudflare Tunnel ou certificado válido no servidor.

## Extensões PHP obrigatórias

No PHP CLI e no PHP-FPM usado pelo domínio, habilite:

- `curl`
- `fileinfo`
- `intl`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_pgsql`
- `pgsql`
- `xml`
- `zip`

No aaPanel, instale pelo painel do PHP do domínio. Instalar via `apt` pode não afetar `/www/server/php/83/bin/php`.

## Binários obrigatórios ou recomendados

- `php`
- `composer` ou `writable/composer/composer.phar`
- `psql`
- `pg_dump`
- `curl`
- `unzip`
- `git`

`pg_dump` é essencial para backup automático antes de ações destrutivas do instalador.

## Diretórios graváveis

O usuário do PHP-FPM precisa gravar em:

- `writable/cache`
- `writable/logs`
- `writable/session`
- `writable/uploads`
- `writable/secrets`
- `writable/installer`
- `writable/composer`
- `writable/cache/composer`

Com aaPanel, normalmente use usuário/grupo `www:www`:

```bash
sudo chown -R www:www writable
sudo chmod -R ug+rwX writable
```

## Variáveis críticas

Produção deve usar:

```dotenv
CI_ENVIRONMENT = production
APP_ENV = production
APP_DEBUG = false
CI_DEBUG = false
KINT_ENABLED = false
app.appTimezone = America/Sao_Paulo
app.defaultLocale = pt-BR
app.baseURL = https://ponto.supportsondagens.com.br/
database.default.DBDriver = Postgre
ALLOW_WEB_INSTALLER = false
SECURITY_PRODUCTION_HARDENING = true
```

Nunca grave senha inicial do admin no `.env`.

## Validação rápida

```bash
bash install/runtime/provision-server.sh --diagnose
php tools/installer/install_cli.php --diagnose
php tools/quality/production-hardening-audit.php
```

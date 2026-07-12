# Problemas comuns — instalação e produção

## Tela branca ou erro 500

Ações:

```bash
php -v
php -m
php tools/installer/install_cli.php --diagnose
ls -lah writable/logs
 tail -n 200 writable/logs/log-$(date +%Y-%m-%d).php
```

Causas comuns:

- `vendor/` ausente.
- Extensão PHP ausente.
- Permissão negada em `writable/`.
- `.env` incompleto.
- `CI_ENVIRONMENT` incorreto.

## `vendor/autoload.php` ausente

```bash
composer install --no-dev --optimize-autoloader
```

No aaPanel, confirme o PHP CLI correto:

```bash
/www/server/php/83/bin/php composer.phar install --no-dev --optimize-autoloader
```

## `pdo_pgsql` ou `pgsql` ausente

No aaPanel, abra PHP do domínio e instale `pgsql`/`pdo_pgsql`. Depois reinicie PHP-FPM pelo painel.

Valide:

```bash
/www/server/php/83/bin/php -m | grep -Ei 'pgsql|pdo_pgsql'
```

## `open_basedir` bloqueando Composer ou pg_dump

Mensagem típica: `open_basedir restriction in effect`.

Correção:

- Executar ações de Composer/backup via CLI/SSH.
- Ajustar open_basedir do domínio no aaPanel para incluir caminhos necessários.
- Não depender do instalador web para tarefas de root/sistema.

## Instalador bloqueado

Verifique:

```bash
ls -lah writable/installer/installed.lock
php tools/installer/install_cli.php --diagnose
```

Em produção, o bloqueio é esperado. Para reinstalação real, use CLI, backup, janela de manutenção e confirmação destrutiva forte.

## Loop ou falha de HTTPS com Cloudflare

Confirme:

- `app.baseURL` com `https://`.
- Proxy envia `X-Forwarded-Proto: https`.
- HSTS só ativado após HTTPS validado.
- Cloudflare não está alternando entre Flexible e Full de forma incompatível.

## Health check público expõe pouco

`/healthz` deve expor apenas liveness. Para detalhes use admin ou token interno em `/healthz/detailed`.

## DeepFace indisponível

O sistema principal não deve cair. Verifique:

- tela Admin > Saúde;
- logs do DeepFace;
- estado do circuit breaker;
- variáveis `DEEPFACE_API_URL`, timeouts e limites biométricos.

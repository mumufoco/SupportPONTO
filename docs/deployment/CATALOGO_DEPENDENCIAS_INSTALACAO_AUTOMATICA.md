# Catálogo de dependências e instalação automática — SupportPONTO v1.1.464

## Objetivo

Este documento consolida todas as dependências e aplicações de terceiros necessárias ou recomendadas para instalar, operar, testar e manter o SupportPONTO.

O catálogo operacional fica em:

```bash
install/runtime/dependencies.catalog.json
```

O script executor fica em:

```bash
install/runtime/install-dependencies.sh
```

## Regra de segurança

O instalador web/PHP não usa `sudo` e não deve tentar instalar pacotes do sistema diretamente. Dependências de sistema exigem execução por CLI com usuário `root` ou `sudo`.

Separação adotada:

| Tipo | Pode ser instalado pelo instalador PHP/CLI sem root? | Observação |
|---|---:|---|
| Composer/vendor PHP | Sim | Quando Composer ou composer.phar estiver disponível. |
| composer.phar local | Sim | Depende de `curl` ou `allow_url_fopen`. |
| DeepFace em venv Python | Sim, se Python/pip já existirem | Pode consumir CPU/memória. Preferir Docker/serviço isolado em produção. |
| Pacotes Ubuntu/Debian | Não | Exige `sudo`/root. |
| PHP extensions do aaPanel | Não | Devem ser habilitadas no PHP do domínio pelo painel. |
| PostgreSQL/Redis/PgBouncer como serviços | Normalmente não | Exigem root, Docker ou serviço gerenciado. |
| Node/npm/Cypress | Sim, se Node/npm já existirem | Necessário para tooling/testes, não para operação web básica. |

## Comandos principais

Diagnóstico completo:

```bash
bash install/runtime/install-dependencies.sh --diagnose
```

Instalar pacotes de sistema no Ubuntu/Debian:

```bash
sudo bash install/runtime/install-dependencies.sh --install-system --recommended
```

Instalar Composer local e vendor PHP:

```bash
bash install/runtime/install-dependencies.sh --install-composer --install-vendor
```

Instalar DeepFace em ambiente Python isolado:

```bash
bash install/runtime/install-dependencies.sh --install-deepface
```

Instalar dependências Node de testes/ferramentas:

```bash
bash install/runtime/install-dependencies.sh --install-node
```

Rodar tudo que for possível:

```bash
sudo bash install/runtime/install-dependencies.sh --all-root --webserver=nginx
sudo -u www bash install/runtime/install-dependencies.sh --install-vendor
```

Ponte pelo instalador CLI:

```bash
php tools/installer/install_cli.php --dependency-catalog
php tools/installer/install_cli.php --install-dependencies --diagnose
php tools/installer/install_cli.php --install-dependencies --install-vendor
php tools/installer/install_cli.php --install-dependencies --install-deepface
```

## Dependências catalogadas

### Sistema operacional e comandos base

- Ubuntu Server 22.04+; recomendado 24.04 LTS.
- Bash.
- curl.
- unzip.
- git.
- acl.
- ca-certificates.
- software-properties-common.
- lsb-release.

### PHP

- PHP 8.3 CLI.
- PHP-FPM 8.3.
- Extensões obrigatórias:
  - curl;
  - fileinfo;
  - intl;
  - json;
  - mbstring;
  - openssl;
  - pdo;
  - pdo_pgsql;
  - pgsql;
  - xml;
  - zip.
- Extensões recomendadas:
  - gd;
  - exif;
  - bcmath;
  - opcache;
  - redis.

### Composer / PHP vendor

Pacotes de produção declarados no `composer.json`:

- chillerlan/php-qrcode;
- codeigniter4/framework;
- codeigniter4/shield;
- doctrine/dbal;
- firebase/php-jwt;
- guzzlehttp/guzzle;
- minishlink/web-push;
- phpoffice/phpspreadsheet;
- tecnickcom/tcpdf;
- workerman/workerman.

Pacotes de desenvolvimento/testes:

- fakerphp/faker;
- mikey179/vfsstream;
- php-webdriver/webdriver;
- phpstan/phpstan;
- phpunit/phpunit.

### Banco e serviços auxiliares

- PostgreSQL client: `psql` e `pg_dump`.
- PostgreSQL Server 14+; recomendado 16.
- Redis 7.
- PgBouncer 1.22.
- Nginx ou Apache.
- PHP-FPM.

### DeepFace

Runtime:

- Python 3.10+;
- python3-venv;
- python3-pip;
- python3-dev;
- build-essential.

Pacotes em `deepface-api/requirements.txt`:

- Flask;
- flask-cors;
- flask-limiter;
- Werkzeug;
- deepface;
- tensorflow;
- opencv-python;
- Pillow;
- numpy;
- pandas;
- python-dotenv;
- gunicorn;
- mtcnn;
- retina-face;
- jsonschema;
- colorlog.

### Node/tooling

- Node.js 18+;
- npm 9+;
- cypress;
- start-server-and-test;
- fast-glob;
- fs-extra;
- puppeteer;
- npm-run-all.

### Docker/containers

- Docker Engine;
- Docker Compose plugin;
- imagens:
  - postgres:16-alpine;
  - redis:7-alpine;
  - bitnami/pgbouncer:1.22;
  - ghcr.io/mumufoco/supportponto/deepface:local;
  - ghcr.io/mumufoco/supportponto/app:local;
  - dpage/pgadmin4:latest;
  - mailhog/mailhog:latest;
  - rediscommander/redis-commander:latest.

## aaPanel

No aaPanel, cuidado: instalar extensões com `apt-get` pode afetar o PHP do sistema, mas não o PHP vinculado ao domínio.

Checklist no aaPanel:

1. App Store > PHP 8.3.
2. Instalar extensões: `pgsql`, `pdo_pgsql`, `intl`, `mbstring`, `xml`, `zip`, `curl`, `fileinfo`, `gd`, `opcache`.
3. Reiniciar PHP-FPM do domínio.
4. Revisar `open_basedir` para permitir:
   - raiz do projeto;
   - `writable/`;
   - temporários necessários;
   - Composer local/global, se usado.
5. Rodar:

```bash
/www/server/php/83/bin/php tools/installer/install_cli.php --install-dependencies --diagnose --php-bin=/www/server/php/83/bin/php
```

## Validação

Gate automático:

```bash
bash scripts/testing/dependency-catalog-gate.sh
```

Via Composer:

```bash
composer run-script test:dependency-catalog
```


## Atualização do Pacote 462

O Pacote 462 endurece o fluxo automático:

- `--json` deve produzir JSON puro em stdout.
- Use `--all-root` para pacotes do sistema e `--all-userland` para Composer/npm/pip.
- Composer, npm e pip são bloqueados como root por padrão.
- Em aaPanel, use `--aapanel --php-bin=/www/server/php/83/bin/php` e valide extensões no PHP real do painel.
- Escolha explicitamente o webserver com `--webserver=nginx`, `--webserver=apache` ou `--webserver=none`.
- DeepFace local exige Python 3.10 ou 3.11 enquanto TensorFlow 2.15 estiver fixado.

## Atualização v1.1.464

O catálogo passou a ser validado de forma relacional. Todos os perfis precisam referenciar IDs reais de `dependencies`. O comando `--all` foi removido e substituído por `--plan`, `--apply-root` e `--apply-userland`.

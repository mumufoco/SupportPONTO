# Instalação em Ubuntu + aaPanel + PostgreSQL

## 1. Preparar o domínio

No aaPanel, crie o site apontando para:

```text
/www/wwwroot/ponto.supportsondagens.com.br/public
```

Evite apontar o vhost para a raiz do projeto. Se isso ocorrer temporariamente, mantenha as regras de bloqueio do `.htaccess` e do Nginx da documentação de hardening.

## 2. Enviar o pacote

```bash
cd /www/wwwroot
unzip SupportPONTO-v1.1.459-documentacao-tecnica-operacional.zip -d ponto.supportsondagens.com.br
cd ponto.supportsondagens.com.br
```

Se o zip extrair uma pasta intermediária, mova o conteúdo para a raiz do projeto antes de continuar.

## 3. Diagnosticar servidor

```bash
bash install/runtime/provision-server.sh --diagnose --php-bin=/www/server/php/83/bin/php
```

Para preparar servidor novo com root:

```bash
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www --php-bin=/www/server/php/83/bin/php
```

## 4. Instalar dependências PHP

Com Composer global:

```bash
composer install --no-dev --optimize-autoloader
```

Com Composer local criado pelo provisionador:

```bash
/www/server/php/83/bin/php writable/composer/composer.phar install --no-dev --optimize-autoloader
```

## 5. Criar banco PostgreSQL

Exemplo no servidor PostgreSQL:

```sql
CREATE DATABASE supportponto WITH ENCODING 'UTF8';
CREATE USER supportponto_user WITH PASSWORD 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON DATABASE supportponto TO supportponto_user;
```

No PostgreSQL 15+, dentro do banco:

```sql
GRANT USAGE, CREATE ON SCHEMA public TO supportponto_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO supportponto_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO supportponto_user;
```

## 6. Instalar por CLI

```bash
php tools/installer/install_cli.php \
  --install \
  --base-url="https://ponto.supportsondagens.com.br/" \
  --db-host="127.0.0.1" \
  --db-port="5432" \
  --db-name="supportponto" \
  --db-user="supportponto_user" \
  --db-pass="SENHA_FORTE_AQUI" \
  --admin-name="Administrador" \
  --admin-email="admin@supportsondagens.com.br"
```

A senha inicial é exibida apenas uma vez ao final. Salve em cofre seguro e troque no primeiro login.

## 7. Instalar por web

Use web apenas quando necessário. Em produção, habilite temporariamente:

```dotenv
ALLOW_WEB_INSTALLER = true
INSTALLER_PRODUCTION_WEB_OVERRIDE = true
INSTALLER_TOKEN = token-longo-e-aleatorio
INSTALLER_ALLOWED_IPS = seu.ip.publico.aqui
```

Abra:

```text
https://ponto.supportsondagens.com.br/install?installer_token=token-longo-e-aleatorio
```

Após concluir, confirme:

```dotenv
ALLOW_WEB_INSTALLER = false
INSTALLER_PRODUCTION_WEB_OVERRIDE = false
```

O arquivo `writable/installer/installed.lock` também bloqueia reexecução.

## 8. Pós-instalação

```bash
php spark migrate:status
php tools/installer/install_cli.php --diagnose
php tools/quality/production-hardening-audit.php
curl -I https://ponto.supportsondagens.com.br/healthz
```

## 9. Workers

Configure supervisor/systemd para:

```bash
php spark jobs:process --daemon --queues=reports,biometric,notifications,maintenance
```

Use `docs/operations/JOBS_FILAS_PROCESSAMENTO_PESADO.md` para parâmetros de memória, tempo e limpeza.

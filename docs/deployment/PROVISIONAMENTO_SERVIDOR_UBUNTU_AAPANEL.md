# Provisionamento de servidor — Ubuntu/aaPanel

## Objetivo

Preparar o ambiente antes da instalação do SupportPONTO, sem atribuir ao PHP/web installer responsabilidades que dependem de `root` ou `sudo`.

## Script principal

```bash
bash install/runtime/provision-server.sh --diagnose
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www
```

## O que o script verifica

- PHP CLI selecionado.
- Extensões PHP obrigatórias: `curl`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `pgsql`, `xml`, `zip`.
- PostgreSQL client: `psql` e `pg_dump`.
- Composer global ou `composer.phar` local.
- Permissões de `writable/`.
- PHP-FPM.
- Nginx/Apache.
- Integração com diagnóstico do instalador.

## Modos

```bash
bash install/runtime/provision-server.sh --diagnose
sudo bash install/runtime/provision-server.sh --install-system-packages
bash install/runtime/provision-server.sh --install-composer
sudo bash install/runtime/provision-server.sh --fix-permissions --project-user=www --project-group=www
sudo bash install/runtime/provision-server.sh --restart-services
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www
```

## aaPanel

No aaPanel, o PHP do domínio normalmente é independente do PHP instalado via apt/yum. Para validar o PHP correto:

```bash
bash install/runtime/provision-server.sh --diagnose --php-bin=/www/server/php/83/bin/php
```

Se uma extensão aparecer ausente, habilite no aaPanel no PHP do domínio e reinicie o PHP-FPM correspondente.

## open_basedir

Se o instalador web não conseguir localizar Composer, `pg_dump` ou diretórios fora do vhost, não é necessariamente falha da aplicação. É uma restrição do PHP/open_basedir. Soluções seguras:

- Executar o provisionamento via SSH/CLI.
- Usar Composer local em `writable/composer/composer.phar`.
- Ajustar open_basedir apenas para caminhos necessários.
- Nunca desativar restrições globalmente sem análise.

## Pós-provisionamento

```bash
php tools/installer/install_cli.php --diagnose
```

Se o diagnóstico estiver sem bloqueios críticos, prossiga com `/install` ou com o instalador CLI.

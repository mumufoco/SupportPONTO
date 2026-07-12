# Teste de instalação limpa real — Pacote 452

Este teste cria um ambiente isolado para validar o SupportPONTO do zero, com PostgreSQL novo, instalador CLI, migrations, seeds, criação do administrador, smoke tests HTTP e relatório final.

## Comando principal

```bash
bash tools/testing/clean-install-e2e.sh
```

O script não roda diretamente sobre o diretório original por padrão. Ele cria um workspace temporário em:

```text
writable/testing/clean-install/workspace-YYYYMMDD-HHMMSS
```

Isso evita que `.env`, `installed.lock`, relatórios e banco de teste contaminem a árvore principal do pacote.

## O que o teste executa

1. Cria workspace limpo.
2. Sobe PostgreSQL 16 em `docker-compose.clean-install.yml`.
3. Instala dependências Composer no workspace, quando `vendor` estiver ausente.
4. Remove `.env`/`installed.lock` do workspace de teste.
5. Executa `tools/installer/install_cli.php --install`.
6. Executa `php spark migrate:status`.
7. Executa `php spark routes`.
8. Sobe `php spark serve` na porta de teste.
9. Valida `/healthz`.
10. Valida `/auth/login`.
11. Tenta autenticação do admin criado.
12. Tenta carregar `/dashboard`.
13. Executa pós-checagem estrutural do pacote.
14. Gera relatório JSON e Markdown.

## Relatórios

Os relatórios ficam em:

```text
writable/testing/clean-install/reports/
```

Arquivos principais:

```text
clean-install-<run-id>.json
clean-install-<run-id>.md
install-<run-id>.stdout.json
spark-serve-<run-id>.log
```

## Variáveis úteis

```bash
CLEAN_DB_PORT=55432
CLEAN_APP_PORT=18080
CLEAN_DB_NAME=supportponto_clean
CLEAN_DB_USER=supportponto
CLEAN_DB_PASS='SupportPontoCleanInstall#452'
CLEAN_ADMIN_EMAIL=admin.clean.install@example.test
CLEAN_ADMIN_PASSWORD='SupportPonto#Clean452!'
CLEAN_ADMIN_NEW_PASSWORD='SupportPonto#Clean452!Nova'
CLEAN_ADMIN_CPF=11122233344
```

## Opções

```bash
bash tools/testing/clean-install-e2e.sh --keep-containers
bash tools/testing/clean-install-e2e.sh --skip-composer
bash tools/testing/clean-install-e2e.sh --skip-http
bash tools/testing/clean-install-e2e.sh --workspace-atual
bash tools/testing/clean-install-e2e.sh --php-bin=/www/server/php/83/bin/php
```

## Uso em release

```bash
composer test:clean-install
composer test:clean-install:postcheck
```

`composer test:release-critical` também executa a pós-checagem estrutural do Pacote 452.

## Observações importantes

- O teste completo requer Docker, PHP CLI, Composer e extensões PostgreSQL no PHP usado pelo script.
- O PostgreSQL usado é descartável, com volume `tmpfs`.
- Por padrão, os containers são derrubados ao final.
- O script usa confirmação destrutiva apenas dentro do banco descartável do teste.
- A senha temporária do admin não deve aparecer no `.env` nem no relatório persistido do instalador.

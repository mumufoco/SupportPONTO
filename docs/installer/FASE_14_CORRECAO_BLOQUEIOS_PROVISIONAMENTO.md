# Fase 14 — Correção de bloqueios de provisionamento

Versão: **SupportPONTO v1.1.484**

## Objetivo

Corrigir bloqueios reportados pelo instalador quando o ambiente não possui `bash`, `psql`, `pg_dump` ou `vendor/autoload.php`.

## Correções

- `bash` agora é resolvido por caminhos comuns (`/bin`, `/usr/bin`, `/usr/local/bin`) e não apenas pelo `PATH` do PHP/webserver.
- O diagnóstico runtime deixa de transformar `psql` e `pg_dump` em bloqueadores genéricos; agora são avisos com comando de correção.
- `pg_dump` continua obrigatório para reinstalação destrutiva em produção.
- `vendor/autoload.php` ausente deixa de ser bloqueio genérico do diagnóstico; passa a ser bloqueio apenas na etapa real de instalação quando vendor não for autorizado ou quando `--install-vendor` falhar.
- Scripts shell agora possuem `find_bin()` para reduzir falso negativo em aaPanel, cron, PHP-FPM e ambientes com `PATH` limitado.

## Comandos recomendados

```bash
sudo apt-get update
sudo apt-get install -y bash postgresql-client curl unzip git
sudo -u www bash install/runtime/install-dependencies.sh --install-vendor --php-bin=/www/server/php/83/bin/php
```

Em aaPanel, as extensões PHP devem ser habilitadas no PHP do domínio, não apenas no PHP do sistema.

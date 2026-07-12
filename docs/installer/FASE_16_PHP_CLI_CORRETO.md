# Fase 16 — PHP CLI correto no instalador

Release: `v1.1.484`

## Objetivo

Garantir que o instalador use o PHP CLI correto do domínio para Composer, Spark, migrations, seeders e diagnóstico de dependências.

## Problema corrigido

Em ambientes aaPanel/PHP-FPM, `PHP_BINARY` pode apontar para um binário inadequado ou para um PHP diferente do domínio. Isso fazia o instalador diagnosticar extensões no PHP errado e executar `spark migrate`/`db:seed` com runtime incompatível.

## Implementação

- Criado detector `phpCliBinary()`.
- Criado relatório `php_cli` no diagnóstico core.
- O instalador valida `PHP_SAPI === cli` antes de aceitar o binário.
- Binários `php-fpm` são recusados.
- Ordem preferencial em aaPanel:
  - `/www/server/php/83/bin/php`
  - `/www/server/php/82/bin/php`
  - `/www/server/php/81/bin/php`
  - `/www/server/php/80/bin/php`
- Override suportado por:
  - `--php-bin=/caminho/php`
  - `INSTALLER_PHP_BIN=/caminho/php`
  - campo avançado `php_bin` no wizard web.
- `spark()` passou a usar o PHP CLI validado, não `PHP_BINARY`.
- Scripts de dependências recebem o mesmo PHP CLI via `--php-bin`.

## Resultado esperado

O instalador deixa de acusar extensões ausentes no PHP errado e passa a executar Composer/migrations/seeders com o runtime correto do domínio.

# Package 481 — Installer PHP CLI Detection Report

Release: `v1.1.484`

## Resumo

Este pacote corrige o uso indevido de `PHP_BINARY` pelo instalador e cria uma detecção dedicada do PHP CLI real usado por Composer, Spark, migrations e seeders.

## Arquivos principais alterados

- `tools/installer/SupportPontoZeroInstaller.php`
- `install/runtime/install-dependencies.sh`
- `docs/installer/FASE_16_PHP_CLI_CORRETO.md`
- `tests/Feature/InstallerPhase16PhpCliStaticTest.php`

## Controles adicionados

- Validação de SAPI `cli`.
- Rejeição de `php-fpm` como PHP CLI.
- Override por `--php-bin` e `INSTALLER_PHP_BIN`.
- Categoria `php_cli` no diagnóstico core.
- Registro de `php_cli_used_for_spark` no relatório de instalação.

## Validação recomendada

```bash
php tools/installer/install_cli.php --diagnose-core --json
php tools/installer/install_cli.php --diagnose-core --json --php-bin=/www/server/php/83/bin/php
php tests/Feature/InstallerPhase16PhpCliStaticTest.php
```

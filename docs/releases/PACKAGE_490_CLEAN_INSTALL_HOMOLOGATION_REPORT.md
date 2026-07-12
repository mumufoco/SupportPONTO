# Package 490 — Clean Install Homologation

## Versão

SupportPONTO v1.1.492

## Resumo

Este pacote adiciona a homologação real do instalador em ambiente limpo, com PostgreSQL zerado, runner de smoke, auditoria runtime e contrato Docker isolado.

## Arquivos principais

- `docker-compose.installer-test.yml`
- `docker-compose.clean-install.yml`
- `tools/release/run-clean-install-smoke.php`
- `tools/release/audit-clean-install-runtime.php`
- `tools/testing/clean-install-e2e.sh`
- `tests/Feature/InstallerPhase25CleanInstallHomologationStaticTest.php`

## Problemas resolvidos

- Falta de teste real de instalação limpa.
- Dependência excessiva de auditorias estáticas para validar migrations/seeders.
- Ausência de relatório padronizado de smoke clean-install.
- Falta de auditoria runtime de tabelas críticas após instalação.

## Resultado esperado

O instalador passa a ter um caminho oficial para comprovar, em ambiente limpo, que consegue:

1. conectar ao PostgreSQL zerado;
2. preparar `.env`;
3. executar migrations;
4. executar seeders;
5. criar `installed.lock`;
6. validar tabelas essenciais;
7. gerar relatório técnico para suporte.

## Comandos recomendados

```bash
php tools/release/run-clean-install-smoke.php --json --php-bin=/www/server/php/83/bin/php
php tools/release/audit-clean-install-runtime.php --json
```

Para validação estrutural sem Docker:

```bash
php tools/release/run-clean-install-smoke.php --json --dry-run
php tests/Feature/InstallerPhase25CleanInstallHomologationStaticTest.php
```

## Limitação assumida

O pacote não força a execução do teste real em ambientes sem Docker/Composer/PostgreSQL. Nesses casos, o dry-run valida apenas o contrato e a homologação real deve ser executada no servidor de build ou homologação.

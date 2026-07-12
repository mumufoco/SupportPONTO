# Relatório de validação — Pacote 434

## Status

Aprovado nas validações estáticas e estruturais executadas no pacote extraído.

## Comandos executados

```bash
php tools/release/audit-version-consistency.php
php tools/release/audit-route-integrity.php
php tools/release/audit-mvc-integrity.php
php tools/release/audit-service-integrity.php
php tools/release/audit-view-integrity.php
php tools/release/audit-model-schema-integrity.php
php tools/release/audit-employee-module-integrity.php
php tools/release/audit-timesheet-module-integrity.php
php tools/release/audit-report-module-integrity.php
php tools/release/audit-package-integrity.php
php -d open_basedir="/mnt/data/ponto434:/tmp" tools/installer/install_cli.php --diagnose
```

## Resultado dos gates

- Gate de versão: aprovado.
- Gate de rotas: aprovado.
- Gate MVC: aprovado.
- Gate de services: aprovado.
- Gate de views/assets: aprovado.
- Gate Model x Schema: aprovado.
- Gate de colaboradores: aprovado.
- Gate de ponto eletrônico: aprovado.
- Gate de relatórios/exportações: aprovado.
- Gate de pacote completo: aprovado.
- Diagnóstico do instalador com `open_basedir`: executado sem warnings de runtime.

## Contagens finais

- `app_php_files`: 814
- `route_modules`: 11
- `controllers`: 74
- `services`: 272
- `models`: 31
- `views`: 216
- `migrations`: 63
- `feature_tests`: 260
- chamadas `applyResultLimit`: 7

## Observação

Não foi executada instalação limpa real em PostgreSQL neste ambiente. Essa etapa continua planejada para o Pacote 452.

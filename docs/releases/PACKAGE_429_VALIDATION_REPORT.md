# Relatório de validação — Pacote 429

## Versão

```text
SupportPONTO v1.1.429
Pacote: 429
Foco: view-layer-asset-integrity
```

## Validações executadas

```bash
php -l tools/release/audit-view-integrity.php
php -l tools/release/audit-package-integrity.php
php -l tools/installer/SupportPontoZeroInstaller.php
php -l tests/Feature/Package429ViewLayerIntegrityStaticTest.php
php tools/release/audit-version-consistency.php
php tools/release/audit-route-integrity.php
php tools/release/audit-mvc-integrity.php
php tools/release/audit-service-integrity.php
php tools/release/audit-view-integrity.php
php tools/release/audit-package-integrity.php
php -d open_basedir="/mnt/data/sp428:/tmp" tools/installer/install_cli.php --diagnose
```

## Resultado

```text
Gate de versão: aprovado
Gate de rotas: aprovado
Gate MVC: aprovado
Gate de services: aprovado
Gate de views/assets: aprovado
Gate de integridade do pacote: aprovado
Diagnóstico CLI do instalador com open_basedir: aprovado sem warnings
```

## Contagens finais

```text
app_php_files: 808
route_modules: 11
controllers: 74
services: 270
models: 31
views: 216
migrations: 60
feature_tests: 256
view_references: 407
asset_references: 42
```

## Avisos não bloqueantes

```text
Referência dinâmica de view ignorada com prefixo validável: reports/ em app/Controllers/Report/ReportController.php
```

Esse aviso é esperado porque o relatório usa view dinâmica por tipo de relatório. Não bloqueia o Pacote 429.

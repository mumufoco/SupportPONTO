# Relatório de validação — Pacote 428

## Pacote

- Nome: SupportPONTO
- Versão: v1.1.428
- Tipo: pacote completo de código-fonte
- Foco: integridade da camada de services

## Validações executadas

```bash
php -l tools/release/audit-service-integrity.php
php -l scripts/testing/service-integrity-gate.sh # não aplicável: shell script validado por execução
php -l app/Services/LGPD/DataExportCollectorService.php
php -l app/Services/LGPD/DataExportService.php
php -l app/Config/Services.php
php -l tests/Feature/Package428ServiceLayerIntegrityStaticTest.php
php tools/release/audit-version-consistency.php
php tools/release/audit-route-integrity.php
php tools/release/audit-mvc-integrity.php
php tools/release/audit-service-integrity.php
php tools/release/audit-package-integrity.php
bash scripts/testing/service-integrity-gate.sh
php -d open_basedir="/mnt/data/validate428_zip:/tmp" tools/installer/install_cli.php --diagnose
```

## Resultado dos gates

```text
Gate de versão: aprovado
Gate de rotas: aprovado
Gate MVC: aprovado
Gate de services: aprovado
Gate de integridade do pacote: aprovado
Diagnóstico CLI do instalador com open_basedir: sem warnings
```

## Contagens observadas

```text
app_php_files: 790
route_modules: 11
controllers: 74
services: 270
models: 31
views: 198
migrations: 60
feature_tests: 255
service_dependency_references_checked: 579
service_helper_references_checked: 6
```

## Observação

Este pacote não corrige modelagem de banco, migrations ou divergência model/schema. Esses pontos permanecem para os pacotes 430 e 431, conforme roadmap aprovado.

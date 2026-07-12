# Relatório de validação — Pacote 426

## Identificação

- Pacote: `426`
- Versão: `v1.1.426`
- Tipo: `.zip` completo do sistema
- Foco: integridade de rotas

## Comandos executados

```bash
php -l tools/release/audit-route-integrity.php
php -l tools/release/audit-package-integrity.php
php -l tests/Feature/Package426RouteIntegrityStaticTest.php
php tools/release/audit-version-consistency.php --report=/mnt/data/pkg426_version_report_pre.json
php tools/release/audit-route-integrity.php --report=/mnt/data/pkg426_route_report.json
bash scripts/testing/route-integrity-gate.sh
php tools/release/audit-package-integrity.php --report=/mnt/data/pkg426_integrity_report.json
```

## Resultado dos gates

- Gate de versão: aprovado
- Gate de rotas: aprovado
- Gate de integridade do pacote: aprovado

## Contagens principais do gate de rotas

```text
route_modules: 11
route_definitions: 464
named_routes: 461
controller_references: 372
view_references: 3
filter_references: 32
filter_aliases: 19
```

## Contagens principais do gate de pacote

```text
app_php_files: 790
route_modules: 11
controllers: 74
services: 270
models: 31
views: 198
migrations: 60
feature_tests: 253
```

## Observação técnica

Este pacote valida a integridade estática das rotas e suas dependências diretas. Ele não executa ainda uma instalação limpa real com banco PostgreSQL, migrations e login, pois isso está previsto no Pacote 452.

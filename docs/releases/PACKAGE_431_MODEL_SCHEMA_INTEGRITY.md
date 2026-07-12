# Pacote 431 — Integridade Model x Schema

## Objetivo

Alinhar os `Models` do SupportPONTO com o schema criado pelas migrations, impedindo erro de coluna inexistente em produção.

## Problemas corrigidos

- `EmployeeModel` possuía campos trabalhistas, cadastrais, LGPD e financeiros que não existiam na tabela `employees`.
- `TimePunchModel` usava `location_lat/location_lng`, mas endpoints legados ainda enviavam `latitude/longitude`.
- `JustificationModel` havia sido parcialmente alterado para `date/type/attachment_path`, enquanto fluxos reais ainda gravavam `justification_date/justification_type/attachments`.
- `SettingModel` aceitava aliases `setting_key/setting_value/setting_type/setting_group`, mas o schema canônico usava `key/value/type/group`.
- `WarningModel` usa soft delete, mas a tabela precisava garantir `deleted_at`.
- O pacote não tinha gate bloqueante para impedir regressão entre `$allowedFields`, validações e migrations.

## Arquivos principais alterados/criados

- `app/Database/Migrations/2026-05-12-0431_AlignModelsWithDatabaseSchema.php`
- `app/Models/TimePunchModel.php`
- `app/Models/JustificationModel.php`
- `app/Database/Migrations/2026-02-01-0038_create_employees_table.php`
- `tools/release/audit-model-schema-integrity.php`
- `scripts/testing/model-schema-integrity-gate.sh`
- `tests/Feature/Package431ModelSchemaIntegrityStaticTest.php`
- `tools/release/audit-package-integrity.php`
- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`
- `composer.json`

## Validação criada

Novo gate:

```bash
php tools/release/audit-model-schema-integrity.php
bash scripts/testing/model-schema-integrity-gate.sh
composer run audit:model-schema
composer run test:model-schema-integrity
```

O gate valida:

- Models com tabela declarada;
- `$allowedFields` existentes nas migrations;
- campos em `validationRules` fora de `allowedFields`;
- soft deletes com coluna `deleted_at`;
- contratos críticos de `employees`, `time_punches`, `justifications` e `settings`.

## Resultado esperado

Instalações novas passam a criar as colunas necessárias para os Models gravarem sem erro de coluna inexistente. Instalações existentes recebem a migration de alinhamento de forma idempotente e não destrutiva.

# Relatório de validação — Pacote 425

## Versão

- Versão: `v1.1.425`
- Pacote: `425`
- Tipo: `complete-source-package`
- Foco: `version-normalization`

## Validações executadas

```bash
php -l tools/release/version-surfaces.php
php -l tools/release/sync-version.php
php -l tools/release/audit-version-consistency.php
php -l tools/release/audit-package-integrity.php
php -l tests/Feature/Package425VersionConsistencyStaticTest.php
php tools/release/sync-version.php --version=1.1.425 --generated-at=2026-05-12T14:38:00-03:00
php tools/release/audit-version-consistency.php --report=/mnt/data/pkg425_version_report.json
php tools/release/audit-package-integrity.php --report=/mnt/data/pkg425_integrity_report.json
```

## Resultado

- Gate de versão aprovado.
- Gate de integridade do pacote aprovado.
- Metadados principais sincronizados em `1.1.425` / `v1.1.425` / pacote `425`.
- Scripts de release chamam o gate de versão e o gate de integridade.
- O pacote final permanece como `.zip` completo do sistema.

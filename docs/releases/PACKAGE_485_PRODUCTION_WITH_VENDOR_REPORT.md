# Package 485 — Production with Vendor Build

Release: `v1.1.489`

## Escopo

Este pacote adiciona o build oficial para gerar uma distribuição de produção com `vendor/` incluído, manifesto de release e checksums SHA256.

## Arquivos adicionados

- `tools/release/build-production-with-vendor.php`
- `tests/Feature/InstallerPhase20ProductionWithVendorStaticTest.php`
- `docs/installer/FASE_20_PACOTE_PRODUCAO_VENDOR.md`
- `docs/releases/PACKAGE_485_PRODUCTION_WITH_VENDOR_REPORT.md`

## Garantias adicionadas

- O artefato `production-with-vendor` exige `vendor/autoload.php`.
- O script executa Composer com flags de produção.
- O build gera manifesto JSON.
- O build gera SHA256 do pacote.
- O fallback sem vendor é explicitamente marcado para evitar confusão operacional.

## Comando oficial

```bash
php tools/release/build-production-with-vendor.php --json
```

## Resultado esperado em ambiente com Composer/vendor

```text
build/release/SupportPONTO-v1.1.489-production-with-vendor.zip
build/release/release-manifest.json
build/release/release-checksums.sha256
```

## Resultado em ambiente sem Composer/vendor

O script falha por padrão, pois não deve gerar um falso pacote de produção sem `vendor/`.

Para gerar pacote fonte de contingência:

```bash
php tools/release/build-production-with-vendor.php --allow-source-fallback --json
```

# Package 493 — Production with vendor real

## Resultado

Este pacote endurece o contrato de build para o artefato `production-with-vendor`.

## Entregas

- Atualização de versão para `1.1.496`.
- Build script atualizado: `tools/release/build-production-with-vendor.php`.
- Auditoria bloqueante adicionada: `tools/release/audit-production-with-vendor-artifact.php`.
- Teste estático adicionado: `tests/Feature/InstallerPhase28ProductionWithVendorStaticTest.php`.
- Documentação adicionada: `docs/installer/FASE_28_PRODUCTION_WITH_VENDOR_REAL.md`.

## Contrato

O artefato de produção somente é válido quando contém `vendor/autoload.php`. Se o ambiente local não tiver Composer/vendor, o build pode gerar fallback de código fonte, mas esse fallback não pode ser anunciado como production-with-vendor.

## Limitação do ambiente de empacotamento

Ambientes sem Composer e sem acesso à internet não conseguem materializar o diretório `vendor/`. Nesses casos, execute o build em servidor/CI com Composer disponível.

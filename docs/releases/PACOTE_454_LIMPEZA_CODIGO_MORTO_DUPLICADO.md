# Pacote 454 — Limpeza de código morto e duplicado

## Objetivo

Reduzir código morto, arquivos duplicados e artefatos gerados que aumentavam ruído técnico e risco de manutenção.

## Problemas tratados

- Artefatos antigos de build/teste estavam sendo enviados dentro do pacote.
- Cache Python do DeepFace estava versionado.
- Wrappers `*LegacyCore` de serviços de relatórios/mensageria não tinham uso interno ativo e duplicavam a superfície de manutenção.
- Faltava um gate objetivo para impedir que esses artefatos voltassem nos próximos pacotes.

## Arquivos removidos

- `deepface-api/__pycache__/`
- `build/go-live-gate/`
- `build/final-production-readiness/`
- `build/installer-runtime-check/`
- `build/runtime-validation/`
- `app/Services/CSVServiceLegacyCore.php`
- `app/Services/ReportServiceLegacyCore.php`
- `app/Services/SMSServiceLegacyCore.php`
- `app/Services/TXTServiceLegacyCore.php`
- `app/Support/LegacyServiceMap.php`

## Arquivos criados/alterados

- `tools/quality/dead-code-audit.php`
- `docs/quality/LIMPEZA_CODIGO_MORTO_DUPLICADO.md`
- `tests/Feature/Package454DeadCodeCleanupStaticTest.php`
- `tests/Feature/Package379LegacyServiceCompatibilityStaticTest.php`
- `tests/unit/Services/LegacyCoreWrapperSimplificationTest.php`
- `tools/quality/essential-test-runner.php`
- `composer.json`
- `public/version.json`
- `Dockerfile`

## Validação esperada

```bash
php tools/quality/dead-code-audit.php
bash scripts/testing/essential-regression-gate.sh
composer test:dead-code
composer test:release-critical
```

## Resultado esperado

Pacote mais limpo, com menor duplicação ativa e com auditoria bloqueante para evitar retorno de artefatos gerados, wrappers obsoletos e referências quebradas simples.

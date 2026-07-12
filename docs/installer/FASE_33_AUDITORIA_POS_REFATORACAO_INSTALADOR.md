# Fase 33 — Auditoria pós-refatoração do instalador

Versão: **SupportPONTO v1.1.498**

## Objetivo

Adicionar um gate específico para impedir regressões depois da refatoração real do instalador iniciada no pacote 497.

A fase garante que a extração para `Core`, `Diagnostics` e `Steps` continue operacional e que os comandos principais do instalador permaneçam JSON-safe.

## Escopo técnico

Arquivos principais:

- `tools/release/audit-installer-refactor-runtime.php`
- `tools/installer/Core/InstallerPaths.php`
- `tools/installer/Core/PhpCliDetector.php`
- `tools/installer/Core/ProcessRunner.php`
- `tools/installer/Diagnostics/CoreDiagnostic.php`
- `tools/installer/Diagnostics/AdvancedDiagnostic.php`
- `tools/installer/Steps/ComposerStep.php`
- `tools/installer/Steps/DatabaseStep.php`
- `tools/installer/SupportPontoZeroInstaller.php`

## O que o novo gate valida

1. Versão e pacote `1.1.498` nos metadados principais.
2. Existência dos componentes extraídos do instalador.
3. Contratos mínimos das classes extraídas.
4. Delegação real no instalador principal.
5. Uso do `ProcessRunner` com timeout/kill seguro.
6. JSON parseável em:
   - `--diagnose-core --json`
   - `--diagnose-full --json`
   - `--offline-resources --json`
   - `--server-provision-plan --json`

## Comandos

Auditoria completa:

```bash
php tools/release/audit-installer-refactor-runtime.php --json
```

Auditoria somente estática:

```bash
php tools/release/audit-installer-refactor-runtime.php --json --static-only
```

Gerar relatório:

```bash
php tools/release/audit-installer-refactor-runtime.php \
  --json \
  --report=writable/installer/installer-refactor-runtime-last.json
```

## Observação

Esta fase não inclui `vendor/`. O pacote permanece como artefato fonte, com pipeline oficial para geração dos artefatos `production-with-vendor` e offline quando o ambiente de build tiver Composer/vendor disponíveis.

# Fase 31 — Offline installer completo

Release: **SupportPONTO v1.1.496**

## Objetivo

Preparar o instalador para publicar um artefato offline honesto, validável e bloqueante, sem depender de download em runtime.

## Contrato do artefato offline completo

Um pacote `offline-installer` só é considerado completo quando contém:

- `vendor/autoload.php`;
- `install/runtime/offline/composer.phar`;
- `install/runtime/checksums.json` com SHA256 real do Composer offline;
- manifesto `offline-release-manifest.json` declarando `offline_ready=true`;
- checksums externos do artefato.

## Comandos

Gerar artefato offline completo:

```bash
php tools/release/build-offline-installer.php --json
```

Gerar fallback fonte, apenas para auditoria/pipeline:

```bash
php tools/release/build-offline-installer.php --json --allow-source-fallback
```

Auditar artefato offline completo:

```bash
php tools/release/audit-offline-installer-artifact.php \
  --artifact=build/release/SupportPONTO-v1.1.496-offline-installer.zip \
  --manifest=build/release/offline-release-manifest.json \
  --checksums=build/release/offline-release-checksums.sha256 \
  --json
```

## Observação

O pacote fonte não deve afirmar que é offline completo quando `vendor/` ou `composer.phar` offline não estiverem presentes.

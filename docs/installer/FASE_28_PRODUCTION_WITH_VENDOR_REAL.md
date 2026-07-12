# Fase 28 — Production with vendor real

## Objetivo

Formalizar a geração do artefato de produção realmente instalável pelo navegador: `SupportPONTO-v1.1.496-production-with-vendor.zip`.

## Regra obrigatória

Um pacote anunciado como `production-with-vendor` **precisa conter**:

- `vendor/autoload.php`
- `composer.lock`
- manifesto `release-manifest.json`
- checksums `release-checksums.sha256`

Se `vendor/autoload.php` estiver ausente, o build deve falhar ou gerar somente um fallback claramente marcado como `source-fallback-without-vendor`.

## Comando de build

```bash
php tools/release/build-production-with-vendor.php --json --php-bin=/www/server/php/83/bin/php
```

## Auditoria do artefato

```bash
php tools/release/audit-production-with-vendor-artifact.php \
  --artifact=build/release/SupportPONTO-v1.1.496-production-with-vendor.zip \
  --manifest=build/release/release-manifest.json \
  --checksums=build/release/release-checksums.sha256 \
  --json
```

## Observação operacional

O pacote fonte v1.1.496 pode ser gerado sem `vendor/`, mas ele não deve ser usado como pacote leigo de produção. Para instalação web sem Composer no servidor, use o artefato `production-with-vendor` gerado em ambiente de build com Composer e internet/cache disponíveis.

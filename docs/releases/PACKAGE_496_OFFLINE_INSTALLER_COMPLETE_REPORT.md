# Pacote 496 — Offline installer completo

Release: **SupportPONTO v1.1.496**

## Entregas

- Criado `tools/release/build-offline-installer.php`.
- Criado `tools/release/audit-offline-installer-artifact.php`.
- Atualizado `install/runtime/checksums.json` para o contrato offline.
- Criado `install/runtime/offline/README.md`.
- Criado teste estático `InstallerPhase31OfflineInstallerCompleteStaticTest.php`.
- Instalador marcado como `1.1.496-offline-installer-complete`.

## Regra de segurança

O build offline completo é bloqueante: só gera artefato `offline-installer` quando `vendor/autoload.php`, `composer.phar` offline e checksum válido existem.

## Resultado esperado

O projeto passa a ter pipeline verificável para gerar um pacote offline real, sem fingir prontidão em ambientes de build sem Composer/vendor.

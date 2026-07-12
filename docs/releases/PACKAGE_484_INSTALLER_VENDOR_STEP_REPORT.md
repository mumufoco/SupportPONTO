# Package 484 — Installer Vendor/Composer Step Report

Release: `v1.1.484`

## Entrega

Este pacote introduz uma etapa explícita para resolver `vendor/autoload.php` antes do banco de dados e antes da execução do CodeIgniter `spark`.

## Problemas resolvidos

- O usuário não recebe mais uma falha tardia de vendor durante migrations.
- O antigo checkbox dentro da tela de banco foi substituído por uma etapa própria.
- A dependência de `INSTALLER_ALLOW_WEB_VENDOR_INSTALL=true` deixou de ser o único caminho de liberação da Web.
- A ação Web agora exige confirmação explícita e continua auditada.

## Artefatos

- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/installer/assets/installer.js`
- `tests/Feature/InstallerPhase19VendorStepStaticTest.php`
- `docs/installer/FASE_19_ETAPA_VENDOR_COMPOSER.md`

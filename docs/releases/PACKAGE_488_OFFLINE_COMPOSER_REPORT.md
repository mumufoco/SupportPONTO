# Package 488 — Offline Composer Installer

Versão: 1.1.489

## Entregas

- Detecção de Composer offline em `install/runtime/offline/composer.phar`.
- Validação obrigatória por SHA256 em `install/runtime/checksums.json`.
- Modo `INSTALLER_OFFLINE_MODE=true` para impedir downloads em runtime.
- Relatório `writable/installer/offline-resources-last.json`.
- CLI `--offline-resources --json`.
- Rota web `?action=offline-resources-json`.

## Segurança

Composer offline existente sem checksum válido é recusado.

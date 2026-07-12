# Fase 23 — Instalador offline e Composer local validado

Esta fase adiciona suporte explícito a instalação com internet restrita.

## Recursos

- `INSTALLER_OFFLINE_MODE=true` impede download de Composer pela internet.
- `install/runtime/offline/composer.phar` passa a ser detectado antes de Composer global.
- `install/runtime/checksums.json` deve conter o SHA256 do Composer offline.
- `php tools/installer/install_cli.php --offline-resources --json` mostra recursos offline disponíveis.
- `?action=offline-resources-json` expõe relatório técnico sanitizado no instalador web.

## Recomendação

O caminho preferencial continua sendo usar o pacote `production-with-vendor`. O Composer offline é alternativa para ambientes bloqueados por firewall.

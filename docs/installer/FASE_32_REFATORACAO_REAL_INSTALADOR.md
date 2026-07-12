# Fase 32 — Refatoração real do instalador

Versão: **SupportPONTO v1.1.497**

## Objetivo

Reduzir o monólito do instalador automático, extraindo lógica operacional real para componentes autocontidos em `tools/installer/Core`, `tools/installer/Diagnostics` e `tools/installer/Steps`, sem depender de Composer/CodeIgniter e sem quebrar as entradas existentes.

## Alterações aplicadas

- `InstallerCoreDiagnostic` deixou de ser marcador e passou a orquestrar o diagnóstico core.
- `InstallerAdvancedDiagnostic` deixou de ser marcador e passou a controlar a fronteira entre diagnóstico core e diagnóstico avançado.
- `InstallerComposerStep` passou a centralizar a política da etapa Vendor/Composer.
- `InstallerDatabaseStep` passou a executar comandos `spark` com PHP CLI validado, ambiente isolado e artefatos de saída.
- `SupportPontoZeroInstaller.php` passou a delegar diagnóstico, decisão de Composer/vendor e execução de `spark` aos componentes extraídos.

## Entradas preservadas

- `/install`
- `tools/installer/install_web.php`
- `tools/installer/install_cli.php`
- `php tools/installer/install_cli.php --diagnose-core --json`
- `php tools/installer/install_cli.php --diagnose-full --json`
- `php tools/installer/install_cli.php --server-provision-plan --json`
- `php tools/installer/install_cli.php --offline-resources --json`

## Critérios

- Componentes novos continuam autocontidos.
- Nenhum componente novo depende de `vendor/`.
- Diagnóstico core e full continuam retornando JSON parseável.
- `spark` continua usando PHP CLI validado.
- A decisão de vendor continua segura e explícita.

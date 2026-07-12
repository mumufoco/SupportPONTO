# Package 486 — Diagnostic UX Separation

Release: `v1.1.489`

## Entrega

Este pacote reorganiza o diagnóstico do instalador para separar claramente:

1. Bloqueia instalação agora.
2. Bloqueia apenas atualização/reinstalação.
3. Recomendado.
4. Opcional pós-instalação.

## Arquivos principais

- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/installer/assets/installer.css`
- `docs/installer/FASE_21_SEPARACAO_BLOQUEIOS_AVISOS.md`
- `tests/Feature/InstallerPhase21DiagnosticUxStaticTest.php`

## Validação esperada

- `php tools/installer/install_cli.php --diagnose-core --json`
- `php tools/installer/install_cli.php --diagnose-full --json`
- `php tests/Feature/InstallerPhase21DiagnosticUxStaticTest.php`

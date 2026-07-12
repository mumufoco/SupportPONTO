# Package 483 — Installer Advanced Diagnostics

Release: `v1.1.484`

## Escopo

Correção do diagnóstico avançado do instalador para evitar travamentos em verificações opcionais de dependências e biometria.

## Arquivos principais

- `tools/installer/SupportPontoZeroInstaller.php`
- `install/runtime/biometric-doctor.sh`
- `tests/Feature/InstallerPhase18AdvancedDiagnosticsStaticTest.php`
- `docs/installer/FASE_18_DIAGNOSTICO_AVANCADO_SEGURO.md`

## Validações esperadas

- `php tools/installer/install_cli.php --diagnose-full --json`
- `php tools/installer/install_cli.php --diagnose-full-light --json`
- `bash install/runtime/biometric-doctor.sh --json --light --timeout=2`
- `php tests/Feature/InstallerPhase18AdvancedDiagnosticsStaticTest.php`

## Resultado

O instalador passa a separar diagnóstico avançado leve e diagnóstico biométrico strict, removendo imports pesados do fluxo padrão e reduzindo risco de travamento.

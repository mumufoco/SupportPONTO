# Package 494 — Advanced diagnostics JSON-safe report

## Resumo

Este pacote corrige o contrato de saída dos diagnósticos avançados do instalador. Em modo `--json`, os scripts runtime passam a reservar stdout para JSON puro, enquanto logs humanos seguem para `stderr` e arquivos em `writable/installer`.

## Arquivos principais

- `tools/installer/SupportPontoZeroInstaller.php`
- `install/runtime/install-dependencies.sh`
- `install/runtime/biometric-doctor.sh`
- `install/runtime/provision-server.sh`
- `tests/Feature/InstallerPhase29AdvancedDiagnosticsJsonSafeStaticTest.php`

## Resultado esperado

O comando `php tools/installer/install_cli.php --diagnose-full --json` deve retornar JSON parseável, sem misturar logs no stdout, mesmo quando o ambiente possui dependências ausentes.

# Package 482 — Installer ProcessRunner robusto

Release: `v1.1.484`

## Resumo

O instalador recebeu uma camada dedicada de execução de processos para reduzir travamentos operacionais. A chamada antiga baseada diretamente em `proc_open()` foi substituída por `InstallerProcessRunner`, com timeout real, limite de saída e tentativa progressiva de encerramento.

## Arquivos principais

- `tools/installer/Core/ProcessRunner.php`
- `tools/installer/SupportPontoZeroInstaller.php`
- `tests/Feature/InstallerPhase17ProcessRunnerStaticTest.php`
- `docs/installer/FASE_17_PROCESS_RUNNER_ROBUSTO.md`

## Validações esperadas

```bash
php -l tools/installer/Core/ProcessRunner.php
php -l tools/installer/SupportPontoZeroInstaller.php
php tools/installer/install_cli.php --diagnose-core --json
php tools/installer/install_cli.php --diagnose-full --json
php tests/Feature/InstallerPhase17ProcessRunnerStaticTest.php
```

## Resultado esperado

Nenhum comando externo do instalador deve travar sem limite. Em caso de timeout, o instalador deve retornar JSON/erro controlado com `timed_out=true` e `exit_code=124`.

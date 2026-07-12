# Fase 17 — ProcessRunner robusto do instalador

Release: `v1.1.484`
Pacote: `482`

## Objetivo

Impedir que comandos externos chamados pelo instalador travem indefinidamente em produção, especialmente Composer, scripts shell, Python, DeepFace, TensorFlow, `pg_dump`, `psql` e comandos auxiliares de diagnóstico.

## Alterações implementadas

- Criado `tools/installer/Core/ProcessRunner.php`.
- `SupportPontoZeroInstaller::cmd()` passou a delegar execução para `InstallerProcessRunner`.
- O runner aplica timeout por comando.
- O runner usa `timeout --kill-after=5s` quando disponível no sistema.
- O runner mantém fallback interno com `proc_terminate(SIGTERM)` e `proc_terminate(SIGKILL)`.
- A saída de comandos é limitada para evitar estouro de memória.
- O retorno passou a incluir metadados operacionais:
  - `timed_out`
  - `duration_ms`
  - `command`
  - `used_timeout_wrapper`
  - `terminated`
- O último processo executado é registrado em `report.artifacts.last_process_runner`.

## Problemas resolvidos

- `--diagnose-full` não deve mais travar indefinidamente.
- Composer não deve prender o instalador sem retorno.
- Python/TensorFlow/DeepFace ficam contidos por timeout.
- Scripts shell longos retornam diagnóstico em vez de congelar o fluxo.
- O suporte técnico passa a ter tempo de execução e status de timeout no relatório.

## Observações

Este pacote não altera a política de quais diagnósticos são executados. Ele corrige a camada de execução para que comandos externos sempre tenham limite operacional. O refinamento de níveis leves/strict do diagnóstico biométrico fica para o pacote seguinte.

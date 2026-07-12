# Fase 29 — Diagnóstico avançado JSON-safe

## Objetivo

Garantir que os diagnósticos avançados do instalador emitam **JSON puro em stdout** quando usados com `--json`, mantendo logs humanos em `stderr` e arquivos de log.

## Correções

- `install/runtime/install-dependencies.sh --diagnose --json` agora mantém stdout reservado para JSON.
- `install/runtime/biometric-doctor.sh --json --light` mantém stdout reservado para JSON.
- `install/runtime/provision-server.sh --diagnose --json` mantém stdout reservado para JSON.
- Comandos executados durante ações com `--json` gravam saída em log, não no stdout.
- `disk_free_mb` retorna `unknown` quando o filesystem não permite medição confiável, evitando falso `0 MB`.
- `--diagnose-full` do instalador retorna JSON parseável mesmo quando dependências opcionais estão ausentes.

## Contrato operacional

```text
stdout = JSON puro
stderr = mensagens humanas e avisos
writable/installer/*.log = log técnico completo
exit code = estado operacional
```

## Validação

```bash
bash install/runtime/install-dependencies.sh --diagnose --json --php-bin=/www/server/php/83/bin/php | python3 -m json.tool
bash install/runtime/biometric-doctor.sh --json --light --timeout=2 | python3 -m json.tool
bash install/runtime/provision-server.sh --diagnose --json --php-bin=/www/server/php/83/bin/php | python3 -m json.tool
php tools/installer/install_cli.php --diagnose-full --json | python3 -m json.tool
```

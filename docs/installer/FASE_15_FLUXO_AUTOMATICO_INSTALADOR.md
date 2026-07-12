# Fase 15 — Correção real do fluxo automático do instalador

Versão: **SupportPONTO v1.1.484**

## Objetivo

Corrigir o instalador automático para que a instalação base não seja bloqueada por diagnósticos pesados ou opcionais, especialmente DeepFace, TensorFlow, Python, Node, `psql`, `pg_dump` e scripts shell de provisionamento.

## Alterações principais

- O diagnóstico padrão passou a ser **core**.
- `php tools/installer/install_cli.php --diagnose` agora equivale ao diagnóstico core.
- Criado diagnóstico explícito `--diagnose-core`.
- Criado diagnóstico avançado `--diagnose-full`.
- O diagnóstico biométrico não roda mais no fluxo principal.
- O diagnóstico real de dependências shell não roda mais no fluxo principal.
- DeepFace/Python/TensorFlow passam a ser pós-instalação ou diagnóstico avançado.
- `install-dependencies.sh --diagnose --json` agora recebe `--php-bin` com o PHP real usado pelo instalador.
- `blocking` agora contém somente bloqueios core.
- Bloqueios opcionais ficam em `optional_blocking`.
- O instalador registra política clara de dependências em `dependency_policy`.

## Comandos

Diagnóstico mínimo para instalação base:

```bash
php tools/installer/install_cli.php --diagnose-core --json
```

Diagnóstico avançado completo:

```bash
php tools/installer/install_cli.php --diagnose-full --json
```

Diagnóstico biométrico isolado:

```bash
php tools/installer/install_cli.php --biometric-doctor --json
```

## Regra nova

A instalação base só deve ser bloqueada por requisitos realmente necessários para instalar o sistema:

- PHP correto;
- extensões PHP essenciais;
- funções PHP essenciais;
- arquivos essenciais;
- writable;
- conexão PostgreSQL quando dados forem informados;
- vendor/Composer somente no momento correto da etapa de dependências.

Itens operacionais e biométricos não bloqueiam a instalação base.

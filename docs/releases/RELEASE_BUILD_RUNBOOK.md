# Runbook de build de artefatos

## Pacote-fonte

Gerar:

```bash
bash scripts/release/build-source-package.sh
```

Uso:
- revisão técnica
- auditoria manual
- transferência de código
- base para pipeline de build

## Pacote-release

Pré-requisitos:
- `vendor/` presente
- dependências PHP instaladas de forma controlada
- validação de release executada

Gerar:

```bash
bash scripts/release/build-release-package.sh
```

## Auditorias mínimas

Antes do go-live:

```bash
bash scripts/testing/source-package-audit.sh
bash scripts/testing/release-audit.sh
```

## Observação importante

O artefato entregue nesta conversa é um **source package**, salvo indicação explícita em contrário no `artifact-manifest.json`.

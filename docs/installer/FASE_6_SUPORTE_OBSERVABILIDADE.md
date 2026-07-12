# Fase 6 — Suporte e observabilidade do instalador

## Objetivo

A Fase 6 transforma o instalador em uma superfície de suporte técnico mais rastreável, com correlação por execução, logs estruturados e pacote de diagnóstico sanitizado.

## Entregas

- `install_id` correlacionado em relatórios, logs e eventos.
- Eventos estruturados em `writable/installer/events-YYYY-MM-DD.ndjson`.
- Novo comando rápido:

```bash
php tools/installer/install_cli.php --installer-doctor --json
```

- Novo bundle de suporte sanitizado:

```bash
php tools/installer/install_cli.php --support-bundle --json
```

- Manifesto do último bundle:

```text
writable/installer/support-bundle-last.json
```

- Bundles gerados em:

```text
writable/installer/support-bundles/
```

## Segurança

O bundle usa sanitização para senhas, tokens, secrets, credenciais e PGPASSWORD. O manifesto declara `contains_sensitive_data=false`, mas o pacote ainda deve ser tratado como arquivo técnico interno.

## Escopo

Esta fase não altera páginas públicas, CSS público, JS público, migrations ou estrutura de banco. O foco é exclusivamente no instalador e na capacidade de suporte.

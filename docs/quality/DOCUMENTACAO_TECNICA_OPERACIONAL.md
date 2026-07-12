# Documentação técnica e operacional

Este pacote torna a documentação parte do critério de release.

## Documentos obrigatórios

- `README.md`
- `README_INSTALL.md`
- `docs/README.md`
- `docs/deployment/REQUISITOS_AMBIENTE_PRODUCAO.md`
- `docs/deployment/INSTALACAO_AAPANEL_UBUNTU_POSTGRESQL.md`
- `docs/deployment/INSTALACAO_CLI_WEB.md`
- `docs/operations/BACKUP_RESTORE_ROLLBACK.md`
- `docs/operations/ATUALIZACAO_PRODUCAO.md`
- `docs/operations/TROUBLESHOOTING_INSTALACAO_PRODUCAO.md`
- `docs/operations/RUNBOOK_SUPORTE_TECNICO.md`
- `install/runtime/README_PROVISIONAMENTO.md`

## Critério de aceite

Um técnico deve conseguir:

1. preparar um Ubuntu/aaPanel;
2. instalar dependências;
3. criar PostgreSQL;
4. executar instalador CLI ou web;
5. validar permissões;
6. diagnosticar problemas comuns;
7. fazer backup;
8. atualizar;
9. executar rollback.

## Gate

```bash
php tools/quality/documentation-audit.php
```

O gate falha se documentos essenciais não existirem ou não citarem temas obrigatórios.

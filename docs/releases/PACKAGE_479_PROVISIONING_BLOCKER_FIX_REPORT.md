# Package 479 — Correção de bloqueios de provisionamento

Release: `v1.1.484`

## Problema corrigido

O diagnóstico do instalador podia bloquear a instalação por pendências que deveriam ser tratadas como preparação de ambiente ou ação guiada:

- `bash não encontrado`;
- `Comando psql: ausente`;
- `Comando pg_dump: ausente`;
- `Vendor PHP: ausente`.

## Resultado

- `bash` agora é resolvido por caminhos absolutos comuns antes de ser considerado ausente.
- `psql` e `pg_dump` aparecem como avisos no diagnóstico geral.
- `pg_dump` permanece obrigatório apenas em fluxos destrutivos/backup real.
- `vendor` ausente não bloqueia o diagnóstico; a etapa de instalação indica como instalar vendor com Composer ou pacote offline.
- Adicionado teste estático `InstallerPhase14ProvisioningBlockerStaticTest.php`.

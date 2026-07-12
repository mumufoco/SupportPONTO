# Runbook de suporte técnico

## Checklist de atendimento

1. Identificar versão em `public/version.json`.
2. Rodar diagnóstico do instalador.
3. Conferir `/healthz` e tela Admin > Saúde.
4. Ler logs recentes.
5. Confirmar ambiente, extensões e permissões.
6. Reproduzir o erro em fluxo mínimo.
7. Aplicar correção documentada ou abrir pacote específico.

## Comandos rápidos

```bash
cat public/version.json
php tools/installer/install_cli.php --diagnose
php tools/quality/production-hardening-audit.php
bash install/runtime/provision-server.sh --diagnose
curl -I https://ponto.supportsondagens.com.br/healthz
```

## Fluxos funcionais mínimos

- Login admin.
- Troca de senha obrigatória no primeiro acesso.
- Dashboard.
- Cadastro de funcionário.
- Registro de ponto.
- Geração de relatório assíncrono.
- Consulta de saúde do sistema.

## Onde procurar evidência

- `writable/logs/`
- `writable/installer/last-install-report.json`
- `writable/installer/last-fatal-error.json`
- `writable/installer/destructive-actions.log`
- tabela `audit_logs`
- tabela `async_jobs`
- tela Admin > Saúde

## Regra de segurança

Nunca peça para ativar `APP_DEBUG=true` em produção aberta ao público. Se for indispensável, faça em janela controlada, IP restrito e desative imediatamente.

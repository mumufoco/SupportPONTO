# Instalação CLI e Web — SupportPONTO

## Escolha recomendada

- **CLI**: recomendado para produção, pois evita timeouts, limita exposição do instalador e registra melhor as falhas.
- **Web**: permitido apenas como fluxo guiado temporário e protegido por token/IP.

## Diagnóstico antes de instalar

```bash
php tools/installer/install_cli.php --diagnose
php tools/installer/install_cli.php --dry-run
```

O diagnóstico deve explicar bloqueios sem fatal error.

## Instalação CLI

```bash
php tools/installer/install_cli.php --install \
  --base-url="https://ponto.supportsondagens.com.br/" \
  --db-host="127.0.0.1" \
  --db-port="5432" \
  --db-name="supportponto" \
  --db-user="supportponto_user" \
  --db-pass="SENHA_FORTE" \
  --admin-name="Administrador" \
  --admin-email="admin@supportsondagens.com.br"
```

## Reset destrutivo controlado

Só use para ambiente de teste ou reinstalação planejada. O comando exige confirmação forte:

```bash
php tools/installer/install_cli.php --install --force-reset \
  --confirm-database-reset="APAGAR BANCO SUPPORTPONTO" \
  --db-name="supportponto" \
  --db-user="supportponto_user" \
  --db-pass="SENHA_FORTE"
```

Antes do reset, o instalador tenta backup automático com `pg_dump`. Se `pg_dump` não existir, ele grava manifesto de risco e bloqueia conforme a política de segurança destrutiva.

## Instalação Web

Pré-condições:

- Sem `writable/installer/installed.lock`.
- `ALLOW_WEB_INSTALLER=true`.
- Em produção, também `INSTALLER_PRODUCTION_WEB_OVERRIDE=true`.
- `INSTALLER_TOKEN` definido.
- IP do técnico em `INSTALLER_ALLOWED_IPS`, quando configurado.

URL:

```text
https://ponto.supportsondagens.com.br/install?installer_token=TOKEN
```

## Depois da instalação

- Confirmar login do admin.
- Trocar senha inicial obrigatoriamente.
- Desativar instalador web.
- Fazer backup do `.env` em cofre seguro.
- Registrar versão e horário do deploy.

## Logs úteis

- `writable/logs/installer-YYYY-MM-DD.log`
- `writable/installer/last-install-report.json`
- `writable/installer/last-fatal-error.json`
- `writable/installer/destructive-actions.log`

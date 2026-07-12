# Fase 1 — Segurança e Fluxo do Instalador SupportPONTO

## Objetivo

Esta fase endurece os pontos críticos do instalador automático antes de evoluir UX, dependências e instalação offline.

## Mudanças implementadas

### 1. Modos explícitos de instalação

O instalador agora diferencia formalmente:

- `clean_install`: instalação limpa. Nunca executa `DROP SCHEMA` se houver tabelas existentes.
- `update`: atualização preservando dados. Nunca executa `DROP SCHEMA` e ignora seeders iniciais.
- `destructive_reinstall`: reinstalação destrutiva controlada. Exige confirmação forte e backup SQL real em produção.

### 2. Journal de instalação

Foi criado o arquivo:

```text
writable/installer/install-journal.json
```

Ele registra:

- `install_id`;
- modo selecionado;
- etapa atual;
- eventos concluídos;
- erro final, se houver;
- orientação de recuperação.

### 3. Backup do `.env`

Antes de regravar o `.env`, o instalador cria backup em:

```text
writable/installer/backups/pre-install-env-*.env.bak
```

### 4. Proteção contra operação destrutiva sem backup real

Em `production`, a reinstalação destrutiva é bloqueada se `pg_dump` não gerar um arquivo SQL restaurável.

Manifesto JSON não é mais considerado backup suficiente em produção.

### 5. Primeira abertura segura do instalador web

Quando `.env` ainda não existe e `installed.lock` também não existe, o guard permite abertura GET/read-only do instalador para diagnóstico inicial.

Para ações POST, token continua obrigatório. Se não houver `.env`, o guard gera:

```text
writable/installer/bootstrap.token
```

O token deve ser usado via parâmetro `token` ou header `X-Installer-Token`.

## Comandos principais

### Diagnóstico

```bash
php tools/installer/install_cli.php --diagnose
```

### Instalação limpa

```bash
php tools/installer/install_cli.php --install --mode=clean_install \
  --app-url=https://dominio/ \
  --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass=senha \
  --admin-name="Admin" --admin-email=admin@dominio.com --admin-cpf=00000000000
```

### Atualização preservando dados

```bash
php tools/installer/install_cli.php --install --mode=update \
  --app-url=https://dominio/ \
  --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass=senha \
  --admin-name="Admin" --admin-email=admin@dominio.com --admin-cpf=00000000000
```

### Reinstalação destrutiva controlada

```bash
php tools/installer/install_cli.php --install --mode=destructive_reinstall --force-reset \
  --confirm-database-reset="APAGAR BANCO SUPPORTPONTO" \
  --app-url=https://dominio/ \
  --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass=senha \
  --admin-name="Admin" --admin-email=admin@dominio.com --admin-cpf=00000000000
```

## Critérios de validação

- O instalador abre em GET quando não existe `.env` nem `installed.lock`.
- Ações POST continuam exigindo token.
- `clean_install` bloqueia banco com tabelas existentes.
- `update` não recria schema e não executa seeders iniciais.
- `destructive_reinstall` exige confirmação forte.
- Em produção, `destructive_reinstall` exige backup SQL real via `pg_dump`.
- `install-journal.json` é criado e atualizado durante a instalação.
- `.env` existente é salvo antes de ser regravado.

# Pacote 441 — Segurança destrutiva do instalador

## Objetivo

Impedir perda acidental de dados durante instalação, reinstalação ou reset do SupportPONTO.

## Problema tratado

O instalador do Pacote 440 possuía fluxo profissional, porém ainda executava `DROP SCHEMA IF EXISTS public CASCADE` como etapa normal da instalação. Em banco PostgreSQL com tabelas existentes, isso poderia apagar dados reais se o operador reutilizasse um banco em produção por engano.

## Correções implementadas

### 1. Bloqueio por `installed.lock`

- Se `writable/installer/installed.lock` existir, a instalação fica bloqueada.
- O bloqueio vale para web e CLI.
- O instalador não reinstala por cima de uma instalação marcada como concluída.
- O reset do estado do instalador continua controlado e não apaga banco, `.env` ou `vendor`.

### 2. Confirmação forte para reset de banco

Antes de recriar `schema public`, o instalador conecta ao banco alvo e verifica se existem tabelas de usuário em `public`.

Se existirem tabelas, o reset é considerado destrutivo e só prossegue com confirmação explícita:

```text
APAGAR BANCO NOME_DO_BANCO
```

Exemplo para banco `supportponto`:

```text
APAGAR BANCO SUPPORTPONTO
```

### 3. Modo `--force-reset` somente CLI

Para CLI, reset destrutivo de banco exige:

```bash
php tools/installer/install_cli.php --install \
  --force-reset \
  --confirm-database-reset="APAGAR BANCO SUPPORTPONTO" \
  --app-url=https://ponto.supportsondagens.com.br/ \
  --db-host=127.0.0.1 \
  --db-name=supportponto \
  --db-user=usuario \
  --db-pass=senha \
  --admin-email=admin@dominio.com \
  --admin-cpf=00000000000
```

Sem `--force-reset`, o CLI bloqueia o reset quando o banco já contém tabelas.

### 4. Segurança no instalador web

- A instalação web fica bloqueada se `installed.lock` existir.
- Se o banco já tiver tabelas, a instalação web exige confirmação textual forte.
- O operador recebe aviso claro antes da instalação.
- O reset web permanece limitado ao estado/lock do instalador e exige token válido.

### 5. Backup automático antes de operação destrutiva

Quando o banco já tem tabelas e o reset foi autorizado, o instalador tenta criar backup automático com `pg_dump` antes do `DROP SCHEMA`.

O backup é salvo em:

```text
writable/installer/backups/
```

Se `pg_dump` não estiver disponível ou falhar, o instalador cria um manifesto JSON com os dados de risco detectados e registra aviso técnico. Em produção, valide backup externo antes de confirmar qualquer reset.

### 6. Log de ação destrutiva

Toda tentativa destrutiva é registrada em:

```text
writable/installer/destructive-actions.log
writable/logs/installer-YYYY-MM-DD.log
```

Eventos registrados incluem:

- instalação bloqueada por `installed.lock`;
- reset bloqueado por confirmação ausente;
- reset autorizado;
- início de `DROP SCHEMA public`;
- reset controlado do estado do instalador.

## Arquivos alterados

- `tools/installer/SupportPontoZeroInstaller.php`
- `public/version.json`

## Arquivos criados

- `docs/releases/PACOTE_441_SEGURANCA_DESTRUTIVA_INSTALADOR.md`
- `docs/operations/INSTALADOR_SEGURANCA_DESTRUTIVA.md`
- `tests/Feature/Package441InstallerDestructiveSafetyStaticTest.php`

## Critérios de validação

- O instalador abre sem fatal error.
- A instalação é bloqueada se `installed.lock` existir.
- Banco com tabelas não é apagado sem confirmação explícita.
- CLI exige `--force-reset` para reset destrutivo.
- Web exige frase forte quando o banco contém tabelas.
- Logs de ação destrutiva são gerados.
- Backup via `pg_dump` é tentado antes do reset destrutivo quando possível.

## Resultado esperado

O instalador continua permitindo instalação limpa, mas deixa de oferecer risco oculto de apagar dados por acidente.

# Operação segura do instalador — reset, backup e reinstalação

## Regra principal

Nunca execute instalação sobre banco existente sem backup validado.

O instalador do SupportPONTO agora trata `DROP SCHEMA public CASCADE` como operação destrutiva, não como etapa comum silenciosa.

## Quando `installed.lock` existir

Arquivo:

```text
writable/installer/installed.lock
```

Com esse arquivo presente, a instalação é bloqueada. Isso indica que o sistema já passou por instalação concluída.

Para reinstalar:

1. Gere backup externo do banco e dos arquivos.
2. Valide o backup.
3. Execute reset controlado do estado do instalador, sem apagar banco:

```bash
php tools/installer/install_cli.php --reset --token=TOKEN_DO_ENV
```

4. Só depois execute nova instalação, com confirmação destrutiva se o banco tiver tabelas.

## Reset destrutivo no CLI

Use somente quando quiser apagar o schema `public` do banco alvo:

```bash
php tools/installer/install_cli.php --install \
  --force-reset \
  --confirm-database-reset="APAGAR BANCO SUPPORTPONTO" \
  --app-url=https://ponto.supportsondagens.com.br/ \
  --db-host=127.0.0.1 \
  --db-name=supportponto \
  --db-user=usuario \
  --db-pass=senha \
  --admin-name="Administrador" \
  --admin-email=admin@dominio.com \
  --admin-cpf=00000000000
```

A frase deve acompanhar o nome real do banco em maiúsculas:

```text
APAGAR BANCO NOME_DO_BANCO
```

## Instalação web

A instalação web permite instalação limpa, mas bloqueia reinstalação se `installed.lock` existir.

Se o banco informado já tiver tabelas, a tela exige confirmação forte. Sem a frase exata, o reset do schema é recusado.

## Logs

Verifique sempre:

```text
writable/installer/destructive-actions.log
writable/logs/installer-YYYY-MM-DD.log
writable/installer/last-install-report.json
```

## Backup automático

O instalador tenta usar `pg_dump` antes do reset destrutivo. O backup, quando gerado, fica em:

```text
writable/installer/backups/
```

Em aaPanel, confirme se `pg_dump` está disponível no `PATH` do PHP/CLI. Mesmo com backup automático, mantenha backup externo validado antes de qualquer operação destrutiva.

## Conduta recomendada em produção

- Não deixe `/install` público após a implantação.
- Mantenha `ALLOW_WEB_INSTALLER=false` depois de instalar.
- Use reset destrutivo apenas via CLI, com janela de manutenção e backup testado.
- Preserve os logs do instalador para auditoria.

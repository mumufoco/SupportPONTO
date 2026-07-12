# Backup, restore e rollback

## Itens que precisam de backup

1. Banco PostgreSQL.
2. `.env`.
3. `writable/uploads`.
4. `writable/secrets`.
5. `writable/installer/installed.lock`.
6. Logs relevantes para auditoria, quando exigido por política interna.

## Backup manual antes de atualização

```bash
mkdir -p /backup/supportponto/$(date +%F-%H%M)
cd /www/wwwroot/ponto.supportsondagens.com.br

pg_dump -h "$PGHOST" -p "${PGPORT:-5432}" -U "$PGUSER" -d "$PGDATABASE" \
  -Fc -f /backup/supportponto/$(date +%F-%H%M)/database.dump

cp .env /backup/supportponto/$(date +%F-%H%M)/env.backup
rsync -a writable/uploads/ /backup/supportponto/$(date +%F-%H%M)/writable-uploads/
rsync -a writable/secrets/ /backup/supportponto/$(date +%F-%H%M)/writable-secrets/
```

## Restore do banco

```bash
pg_restore -h "$PGHOST" -p "${PGPORT:-5432}" -U "$PGUSER" -d "$PGDATABASE" \
  --clean --if-exists /backup/supportponto/AAAA-MM-DD-HHMM/database.dump
```

## Rollback de código

1. Colocar aplicação em manutenção, se houver janela operacional.
2. Preservar o `.env` atual.
3. Restaurar pacote anterior validado.
4. Rodar `composer install --no-dev --optimize-autoloader` se necessário.
5. Restaurar `writable/uploads` e `writable/secrets` caso o rollback envolva arquivos.
6. Rodar gates:

```bash
php tools/installer/install_cli.php --diagnose
php tools/quality/production-hardening-audit.php
curl -I https://ponto.supportsondagens.com.br/healthz
```

## Rollback de migrations

Evite rollback automático de migrations em produção sem análise. Muitas migrations criam índices, trilhas de auditoria ou campos LGPD. Se precisar reverter banco, prefira restore do dump completo feito antes da atualização.

## Critério mínimo para liberar após rollback

- `/healthz` responde 200.
- Login admin funciona.
- Dashboard abre sem erro 500.
- Registro de ponto funciona.
- Fila não acumula falhas críticas.
- Logs não mostram fatal error recorrente.

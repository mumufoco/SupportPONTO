# Jobs, filas e processamento pesado — SupportPONTO

## Objetivo

Evitar que requisições web fiquem presas em tarefas de longa duração, especialmente:

- geração de relatórios PDF/XLSX/CSV/TXT/XML/AFD;
- cadastro facial via DeepFace;
- exportações LGPD;
- notificações push;
- backups e tarefas de manutenção.

## Comandos principais

Processamento pontual:

```bash
php spark jobs:process --queues reports,biometric,exports,notifications,maintenance --limit 10
```

Worker em loop controlado:

```bash
php spark jobs:process \
  --daemon \
  --queues reports,biometric,exports,notifications,maintenance \
  --limit 10 \
  --sleep 5 \
  --max-jobs 1000 \
  --max-seconds 3600 \
  --memory-limit 256 \
  --cleanup-temp
```

Limpeza de temporários:

```bash
php spark jobs:cleanup --dry-run
php spark jobs:cleanup --temp-ttl-hours 24 --result-ttl-hours 72
```

## Exemplo systemd

```ini
[Unit]
Description=SupportPONTO Queue Worker
After=network.target postgresql.service

[Service]
Type=simple
WorkingDirectory=/www/wwwroot/ponto.supportsondagens.com.br
ExecStart=/usr/bin/php spark jobs:process --daemon --queues reports,biometric,exports,notifications,maintenance --limit 10 --sleep 5 --max-jobs 1000 --max-seconds 3600 --memory-limit 256 --cleanup-temp
Restart=always
RestartSec=5
User=www
Group=www

[Install]
WantedBy=multi-user.target
```

## Exemplo cron seguro

```cron
* * * * * cd /www/wwwroot/ponto.supportsondagens.com.br && /usr/bin/php spark jobs:process --queues reports,biometric,exports,notifications,maintenance --limit 10 --cleanup-temp >> writable/logs/queue-worker.log 2>&1
15 3 * * * cd /www/wwwroot/ponto.supportsondagens.com.br && /usr/bin/php spark jobs:cleanup >> writable/logs/queue-cleanup.log 2>&1
```

## Variáveis recomendadas

```dotenv
QUEUE_KNOWN_QUEUES='reports,biometric,exports,notifications,maintenance,default'
QUEUE_WORKER_LIMIT=10
QUEUE_WORKER_SLEEP=5
QUEUE_WORKER_MEMORY_MB=256
QUEUE_STALE_AFTER_MINUTES=15
QUEUE_MAX_PAYLOAD_BYTES=8388608
QUEUE_TEMP_FILES_TTL_HOURS=24
QUEUE_RESULT_FILES_TTL_HOURS=72
```

## Validações de produção

1. Rodar migrations para criar/atualizar a tabela `async_jobs`.
2. Confirmar que o worker roda com o mesmo usuário do projeto (`www` em aaPanel).
3. Confirmar que `writable/` está gravável pelo worker.
4. Confirmar que relatórios retornam `202 Accepted` e `job_id` para formatos pesados.
5. Confirmar que cadastro facial via API retorna `202 Accepted` e `job_id`.
6. Confirmar que `jobs:process` conclui jobs e libera download quando aplicável.

## Observações

- O processamento continua exclusivamente via CLI. A aplicação web apenas enfileira e consulta status.
- Jobs presos em `processing` são recuperados após `QUEUE_STALE_AFTER_MINUTES`.
- Arquivos temporários antigos devem ser limpos por `jobs:cleanup` ou pelo `--cleanup-temp` do worker.

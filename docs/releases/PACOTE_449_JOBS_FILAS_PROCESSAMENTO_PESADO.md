# Pacote 449 — Jobs, filas e processamento pesado

## Objetivo

Tirar tarefas pesadas da requisição web para reduzir travamentos, timeouts e consumo excessivo de CPU/memória.

## Escopo executado

- Relatórios pesados permanecem enfileirados via `AsyncJobService`.
- Cadastro facial via API foi alterado para fila biométrica.
- Proxy legado `/deepface/enroll` deixou de processar DeepFace diretamente e passou a retornar job assíncrono.
- Worker CLI `jobs:process` foi reforçado com modo daemon, seleção de filas, limites de tempo, memória e quantidade de jobs.
- Criado comando `jobs:cleanup` para temporários e resultados antigos.
- Criado `TemporaryFileCleanupService`.
- Criado `Config\Queue` para padronizar limites.
- Tabela `async_jobs` recebeu campos de lock e expiração de saída.
- Modelo `AsyncJobModel` recebeu reivindicação segura de jobs para reduzir processamento duplicado por múltiplos workers.
- Criado método `dispatchPushNotification()` para compatibilizar envio push assíncrono já chamado pela API.

## Arquivos principais alterados/criados

- `app/Config/Queue.php`
- `app/Commands/ProcessAsyncJobs.php`
- `app/Commands/CleanupAsyncJobFiles.php`
- `app/Database/Migrations/2026-05-17-0449_QueueHardeningForHeavyProcessing.php`
- `app/Models/AsyncJobModel.php`
- `app/Services/Queue/AsyncJobService.php`
- `app/Services/Queue/TemporaryFileCleanupService.php`
- `app/Services/Biometric/ApiFaceBiometricService.php`
- `app/Controllers/API/ApiController.php`
- `.env.example`
- `.env.production.example`
- `docs/operations/JOBS_FILAS_PROCESSAMENTO_PESADO.md`
- `tests/Feature/Package449AsyncJobsHeavyProcessingStaticTest.php`

## Resultado esperado

- PDF/XLSX/CSV/XML/TXT/AFD não travam a requisição web.
- Cadastro biométrico facial não executa DeepFace diretamente no request de API.
- Jobs têm status consultável e download controlado.
- Worker pode rodar via systemd, supervisor ou cron.
- Jobs presos são recuperados.
- Temporários antigos podem ser removidos com comando seguro.

## Validação

- `php -l` em todos os PHP alterados/criados.
- Varredura estática para confirmar comandos, fila biométrica e métodos de compatibilidade.
- Validação de integridade do ZIP com `unzip -t`.

## Limitação do sandbox

Não foi possível executar `php spark jobs:process` nem PHPUnit porque o pacote base continua sem `vendor/codeigniter4/framework/system/Boot.php` no ambiente de sandbox.

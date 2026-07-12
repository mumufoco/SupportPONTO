<?php

namespace App\Commands;

use App\Services\Queue\AsyncJobService;
use App\Services\Queue\TemporaryFileCleanupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Queue as QueueConfig;

class ProcessAsyncJobs extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'jobs:process';
    protected $description = 'Processa jobs assíncronos pendentes da fila sem travar requisições web.';
    protected $usage = 'jobs:process [--limit 10] [--queue reports] [--queues reports,biometric] [--daemon] [--stop-when-empty] [--sleep 5] [--max-jobs 100] [--max-seconds 300] [--memory-limit 256] [--stale-after 15] [--skip-stale-recovery] [--cleanup-temp] [--dry-run-cleanup]';
    protected $options = [
        '--limit' => 'Quantidade máxima de jobs por ciclo.',
        '--queue' => 'Fila específica a processar.',
        '--queues' => 'Lista de filas separadas por vírgula. Ex.: reports,biometric,exports.',
        '--daemon' => 'Mantém o worker rodando em loop controlado.',
        '--stop-when-empty' => 'No modo daemon, encerra quando nenhum job for processado no ciclo.',
        '--sleep' => 'Segundos entre ciclos no modo daemon.',
        '--max-jobs' => 'Quantidade máxima total de jobs antes de encerrar.',
        '--max-seconds' => 'Tempo máximo de execução antes de encerrar.',
        '--memory-limit' => 'Limite de memória em MB antes de encerrar o worker.',
        '--stale-after' => 'Minutos para considerar um job em processing como preso.',
        '--skip-stale-recovery' => 'Desativa recuperação automática de jobs presos.',
        '--cleanup-temp' => 'Executa limpeza de arquivos temporários ao final do ciclo.',
        '--dry-run-cleanup' => 'Simula a limpeza temporária sem remover arquivos.',
    ];

    public function run(array $params)
    {
        $config = config('Queue');
        $service = new AsyncJobService();

        $limit = max(1, (int) (CLI::getOption('limit') ?? $config->defaultLimit));
        $queues = $this->resolveQueues(CLI::getOption('queues'), CLI::getOption('queue'), $config);
        $daemon = CLI::getOption('daemon') !== null;
        $sleep = max(1, (int) (CLI::getOption('sleep') ?? $config->sleepSeconds));
        $maxJobs = max(1, (int) (CLI::getOption('max-jobs') ?? ($daemon ? 1000 : $limit * count($queues))));
        $maxSeconds = max(1, (int) (CLI::getOption('max-seconds') ?? ($daemon ? 3600 : 300)));
        $memoryLimitMb = max(64, (int) (CLI::getOption('memory-limit') ?? $config->memoryLimitMb));
        $staleAfter = max(1, (int) (CLI::getOption('stale-after') ?? $config->staleAfterMinutes));
        $skipRecovery = CLI::getOption('skip-stale-recovery') !== null;
        $cleanupTemp = CLI::getOption('cleanup-temp') !== null;
        $dryRunCleanup = CLI::getOption('dry-run-cleanup') !== null;

        $startedAt = time();
        $totalProcessed = 0;
        $totalFailed = 0;
        $cycle = 0;

        CLI::write('Worker iniciado: ' . $service->workerId(), 'green');
        CLI::write('Filas: ' . implode(', ', $queues));

        do {
            $cycle++;
            $cycleProcessed = 0;
            $cycleFailed = 0;

            foreach ($queues as $queue) {
                $result = $service->processPending($limit, $queue === 'all' ? null : $queue, ! $skipRecovery, $staleAfter);
                $cycleProcessed += (int) ($result['processed'] ?? 0);
                $cycleFailed += (int) ($result['failed'] ?? 0);

                CLI::write(sprintf(
                    '[ciclo %d][%s] processados=%d falhos=%d recuperados=%d retry=%d failed-stale=%d',
                    $cycle,
                    $queue,
                    (int) ($result['processed'] ?? 0),
                    (int) ($result['failed'] ?? 0),
                    (int) ($result['recovered'] ?? 0),
                    (int) ($result['recovered_to_retry'] ?? 0),
                    (int) ($result['recovered_to_failed'] ?? 0)
                ), ((int) ($result['failed'] ?? 0)) > 0 ? 'yellow' : 'green');
            }

            $totalProcessed += $cycleProcessed;
            $totalFailed += $cycleFailed;

            if ($this->shouldStop($daemon, $startedAt, $maxSeconds, $totalProcessed, $maxJobs, $memoryLimitMb, $cycleProcessed)) {
                break;
            }

            sleep($sleep);
        } while ($daemon);

        if ($cleanupTemp) {
            $cleanup = (new TemporaryFileCleanupService($config))->cleanup($dryRunCleanup);
            CLI::write(sprintf(
                'Limpeza temporária: scanned=%d deleted=%d freed=%d bytes dry-run=%s',
                $cleanup['scanned'],
                $cleanup['deleted'],
                $cleanup['freed_bytes'],
                $cleanup['dry_run'] ? 'sim' : 'não'
            ), $cleanup['success'] ? 'green' : 'yellow');

            foreach ($cleanup['errors'] as $error) {
                CLI::write(' - ' . $error, 'yellow');
            }
        }

        CLI::write('Total processado: ' . $totalProcessed, 'green');
        CLI::write('Total falho: ' . $totalFailed, $totalFailed > 0 ? 'yellow' : 'green');
    }

    /**
     * @return list<string>
     */
    private function resolveQueues(null|string|bool $queuesOption, null|string|bool $queueOption, QueueConfig $config): array
    {
        if (is_string($queuesOption) && trim($queuesOption) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $queuesOption))));
        }

        if (is_string($queueOption) && trim($queueOption) !== '') {
            return [trim($queueOption)];
        }

        return array_values(array_unique($config->knownQueues));
    }

    private function shouldStop(bool $daemon, int $startedAt, int $maxSeconds, int $processed, int $maxJobs, int $memoryLimitMb, int $cycleProcessed): bool
    {
        if (! $daemon) {
            return true;
        }

        if ((time() - $startedAt) >= $maxSeconds) {
            CLI::write('Worker encerrado por limite de tempo.', 'yellow');
            return true;
        }

        if ($processed >= $maxJobs) {
            CLI::write('Worker encerrado por limite de jobs.', 'yellow');
            return true;
        }

        if ((memory_get_usage(true) / 1024 / 1024) >= $memoryLimitMb) {
            CLI::write('Worker encerrado por limite de memória.', 'yellow');
            return true;
        }

        return $cycleProcessed === 0 && CLI::getOption('stop-when-empty') !== null;
    }
}

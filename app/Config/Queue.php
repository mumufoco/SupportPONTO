<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Queue extends BaseConfig
{
    /**
     * Quantidade padrão de jobs processados por ciclo do worker.
     */
    public int $defaultLimit = 10;

    /**
     * Tempo padrão para considerar um job preso em processing.
     */
    public int $staleAfterMinutes = 15;

    /**
     * Segundos entre ciclos no modo daemon.
     */
    public int $sleepSeconds = 5;

    /**
     * Limite de memória recomendado para encerrar o worker de forma segura.
     */
    public int $memoryLimitMb = 256;

    /**
     * Horas para manter arquivos temporários genéricos.
     */
    public int $temporaryFilesTtlHours = 24;

    /**
     * Horas para manter arquivos de resultado de jobs concluídos.
     */
    public int $resultFilesTtlHours = 72;

    /**
     * Filas operacionais conhecidas.
     *
     * @var list<string>
     */
    public array $knownQueues = ['reports', 'biometric', 'exports', 'notifications', 'maintenance', 'default'];

    public function __construct()
    {
        parent::__construct();

        $this->defaultLimit = max(1, (int) env('QUEUE_WORKER_LIMIT', $this->defaultLimit));
        $this->staleAfterMinutes = max(1, (int) env('QUEUE_STALE_AFTER_MINUTES', $this->staleAfterMinutes));
        $this->sleepSeconds = max(1, (int) env('QUEUE_WORKER_SLEEP', $this->sleepSeconds));
        $this->memoryLimitMb = max(64, (int) env('QUEUE_WORKER_MEMORY_MB', $this->memoryLimitMb));
        $this->temporaryFilesTtlHours = max(1, (int) env('QUEUE_TEMP_FILES_TTL_HOURS', $this->temporaryFilesTtlHours));
        $this->resultFilesTtlHours = max(1, (int) env('QUEUE_RESULT_FILES_TTL_HOURS', $this->resultFilesTtlHours));

        $queues = (string) env('QUEUE_KNOWN_QUEUES', implode(',', $this->knownQueues));
        $parsed = array_values(array_filter(array_map('trim', explode(',', $queues))));
        if ($parsed !== []) {
            $this->knownQueues = $parsed;
        }
    }
}

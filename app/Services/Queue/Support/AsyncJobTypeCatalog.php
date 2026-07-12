<?php

declare(strict_types=1);

namespace App\Services\Queue\Support;

/**
 * Catálogo único de tipos de jobs, filas e prioridades.
 *
 * Evita espalhar decisões de roteamento de filas dentro do worker e facilita
 * futuras alterações sem tocar no processamento de cada job.
 */
final class AsyncJobTypeCatalog
{
    /** @var array<string, array{queue: string, priority: int}> */
    private const MAP = [
        'report.generate' => ['queue' => 'reports', 'priority' => 70],
        'lgpd.export' => ['queue' => 'exports', 'priority' => 50],
        'notification.push' => ['queue' => 'notifications', 'priority' => 90],
        'biometric.face_enroll' => ['queue' => 'biometric', 'priority' => 80],
        'admin.database_backup' => ['queue' => 'maintenance', 'priority' => 60],
    ];

    public function queueFor(string $jobType): string
    {
        return self::MAP[$jobType]['queue'] ?? 'default';
    }

    public function priorityFor(string $jobType): int
    {
        return self::MAP[$jobType]['priority'] ?? 50;
    }

    /**
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        return array_keys(self::MAP);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Support\SensitiveDataSanitizer;
use Throwable;

/**
 * Telemetria operacional leve, local e sem dependência externa.
 *
 * Objetivo da Fase 12:
 * - registrar eventos críticos de operação em NDJSON;
 * - padronizar request_id/correlation_id;
 * - sanitizar contexto antes de persistir;
 * - permitir suporte técnico sem vazar segredos.
 */
class OperationalTelemetryService
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?: (WRITEPATH . 'observability'), DIRECTORY_SEPARATOR);
    }

    /** @param array<string,mixed> $context */
    public function record(string $level, string $domain, string $event, array $context = []): void
    {
        $level = $this->normalizeLevel($level);
        $domain = $this->normalizeToken($domain, 'system');
        $event = $this->normalizeToken($event, 'event');

        $payload = [
            'timestamp' => gmdate(DATE_ATOM),
            'level' => $level,
            'domain' => $domain,
            'event' => $event,
            'request_id' => $this->requestId(),
            'correlation_id' => $this->correlationId(),
            'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
            'context' => SensitiveDataSanitizer::sanitizeForTelemetry($context),
        ];

        try {
            if (! is_dir($this->directory)) {
                @mkdir($this->directory, 0775, true);
            }

            $file = $this->directory . DIRECTORY_SEPARATOR . 'operational-events-' . gmdate('Y-m-d') . '.ndjson';
            @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            log_message('warning', '[Observability] Falha ao registrar telemetria operacional: ' . $e->getMessage());
        }
    }

    /** @return array<string,mixed> */
    public function health(): array
    {
        $exists = is_dir($this->directory);
        $writable = $exists ? is_writable($this->directory) : is_writable(dirname($this->directory));
        $latest = $this->latestEventFile();

        return [
            'directory' => 'writable/observability',
            'exists' => $exists,
            'writable' => $writable,
            'latest_event_file' => $latest ? basename($latest) : null,
            'latest_event_size_bytes' => $latest && is_file($latest) ? (int) filesize($latest) : 0,
            'retention_days' => $this->retentionDays(),
        ];
    }

    public function cleanupOldEvents(?int $days = null): int
    {
        $days ??= $this->retentionDays();
        $cutoff = time() - (max(1, $days) * 86400);
        $deleted = 0;

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . 'operational-events-*.ndjson') ?: [] as $file) {
            $mtime = @filemtime($file) ?: time();
            if ($mtime < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @return list<string> */
    public function tail(int $maxLines = 300): array
    {
        $latest = $this->latestEventFile();
        if ($latest === null || ! is_readable($latest)) {
            return [];
        }

        $lines = @file($latest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return [];
        }

        return array_slice($lines, -1 * max(1, $maxLines));
    }

    private function latestEventFile(): ?string
    {
        $latest = null;
        $latestTime = 0;
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . 'operational-events-*.ndjson') ?: [] as $file) {
            $mtime = @filemtime($file) ?: 0;
            if ($mtime >= $latestTime) {
                $latestTime = $mtime;
                $latest = $file;
            }
        }

        return $latest;
    }

    private function requestId(): string
    {
        $requestId = $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        if (! is_string($requestId) || trim($requestId) === '') {
            $requestId = 'req-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(6));
            $_SERVER['SUPPORTPONTO_REQUEST_ID'] = $requestId;
        }

        return substr(preg_replace('/[^a-zA-Z0-9_.:-]/', '', $requestId) ?: $requestId, 0, 120);
    }

    private function correlationId(): string
    {
        if (function_exists('correlation_id')) {
            return correlation_id();
        }

        $id = $_SERVER['SUPPORTPONTO_CORRELATION_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            $id = $this->requestId();
        }

        return substr(preg_replace('/[^a-zA-Z0-9_.:-]/', '', $id) ?: $id, 0, 120);
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        return in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true) ? $level : 'info';
    }

    private function normalizeToken(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.-]+/', '_', $value) ?: '';
        return $value !== '' ? substr($value, 0, 80) : $fallback;
    }

    private function retentionDays(): int
    {
        return max(1, (int) (getenv('OBSERVABILITY_RETENTION_DAYS') ?: env('observability.retentionDays', 30)));
    }
}

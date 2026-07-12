<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Models\SettingModel;
use App\Services\Observability\OperationalTelemetryService;
use App\Services\Biometric\DeepFaceCircuitBreakerService;
use App\Services\Biometric\DeepFaceService;
use CodeIgniter\Database\BaseConnection;
use Config\Queue as QueueConfig;
use Throwable;

/**
 * Serviço central de saúde operacional do SupportPONTO.
 *
 * Regra de exposição:
 * - liveness/readiness retornam apenas dados sanitizados para monitor externo;
 * - detailedHealth/adminHealthItems são para Admin/token interno;
 * - mensagens nunca incluem DSN, usuário, senha, SQL completo ou caminhos sensíveis além de labels.
 */
class SystemHealthCheckService
{
    private ?BaseConnection $db = null;
    private SettingModel $settings;
    private DeepFaceService $deepFace;
    private DeepFaceCircuitBreakerService $deepFaceCircuitBreaker;
    private QueueConfig $queueConfig;

    public function __construct()
    {
        $this->settings = new SettingModel();
        $this->deepFace = new DeepFaceService();
        $this->deepFaceCircuitBreaker = new DeepFaceCircuitBreakerService();
        $this->queueConfig = config('Queue');
    }

    public function liveness(): array
    {
        return [
            'status' => 'alive',
            'timestamp' => gmdate(DATE_ATOM),
            'request_id' => $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? null,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : null,
            'service' => 'supportponto',
            'version' => $this->versionString(),
        ];
    }

    public function readiness(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'writable' => $this->checkWritable(),
            'env' => $this->checkEnvironment(),
            'migrations' => $this->checkMigrations(false),
            'queue' => $this->checkQueue(false),
            'storage' => $this->checkStorage(false),
            'observability' => $this->checkObservability(false),
        ];

        return [
            'status' => $this->isReady($checks) ? 'ready' : 'not_ready',
            'timestamp' => gmdate(DATE_ATOM),
            'request_id' => $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? null,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : null,
            'checks' => $this->publicChecks($checks),
        ];
    }

    public function detailedHealth(): array
    {
        $checks = [
            'database' => $this->checkDatabase(true),
            'writable' => $this->checkWritable(true),
            'migrations' => $this->checkMigrations(true),
            'version' => $this->checkVersion(),
            'queue' => $this->checkQueue(true),
            'deepface' => $this->checkDeepFace(),
            'websocket' => $this->checkWebsocket(),
            'storage' => $this->checkStorage(true),
            'logs' => $this->checkLogs(),
            'observability' => $this->checkObservability(true),
            'env' => $this->checkEnvironment(true),
        ];

        return [
            'status' => $this->aggregateStatus($checks),
            'timestamp' => gmdate(DATE_ATOM),
            'request_id' => $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? null,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : null,
            'summary' => [
                'checks_count' => count($checks),
                'ok_count' => $this->countStatus($checks, 'ok'),
                'warning_count' => $this->countStatus($checks, 'warning'),
                'error_count' => $this->countStatus($checks, 'error'),
                'alerts_count' => count($this->buildAlerts($checks)),
            ],
            'modules' => $checks,
            'alerts' => $this->buildAlerts($checks),
        ];
    }

    public function adminHealthItems(): array
    {
        $details = $this->detailedHealth();
        $modules = $details['modules'] ?? [];

        return [
            [
                'value' => strtoupper((string) ($modules['database']['status'] ?? 'error')),
                'label' => 'Banco de dados',
                'status' => $this->variant((string) ($modules['database']['status'] ?? 'error')),
            ],
            [
                'value' => strtoupper((string) ($modules['queue']['status'] ?? 'error')),
                'label' => 'Fila de jobs',
                'status' => $this->variant((string) ($modules['queue']['status'] ?? 'error')),
            ],
            [
                'value' => strtoupper((string) ($modules['deepface']['status'] ?? 'error')),
                'label' => 'DeepFace',
                'status' => $this->variant((string) ($modules['deepface']['status'] ?? 'error')),
            ],
            [
                'value' => strtoupper((string) ($modules['migrations']['status'] ?? 'error')),
                'label' => 'Migrations',
                'status' => $this->variant((string) ($modules['migrations']['status'] ?? 'error')),
            ],
            [
                'value' => strtoupper((string) ($modules['storage']['status'] ?? 'error')),
                'label' => 'Storage',
                'status' => $this->variant((string) ($modules['storage']['status'] ?? 'error')),
            ],
            [
                'value' => strtoupper((string) ($modules['websocket']['status'] ?? 'warning')),
                'label' => 'WebSocket',
                'status' => $this->variant((string) ($modules['websocket']['status'] ?? 'warning')),
            ],
            [
                'value' => strtoupper((string) ($modules['logs']['status'] ?? 'error')),
                'label' => 'Logs',
                'status' => $this->variant((string) ($modules['logs']['status'] ?? 'error')),
            ],
            [
                'value' => $this->versionString(),
                'label' => 'Versão instalada',
                'status' => $this->variant((string) ($modules['version']['status'] ?? 'warning')),
            ],
        ];
    }

    public function aggregateStatus(array $checks): string
    {
        $hasError = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'error');
            if ($status === 'error') {
                $hasError = true;
            }
            if ($status === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            return 'unhealthy';
        }

        if ($hasWarning) {
            return 'degraded';
        }

        return 'healthy';
    }

    private function db(): BaseConnection
    {
        if ($this->db === null) {
            $this->db = \Config\Database::connect();
        }

        return $this->db;
    }

    private function checkDatabase(bool $detailed = false): array
    {
        try {
            $db = $this->db();
            $started = microtime(true);
            $db->query('SELECT 1');
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            $meta = $detailed ? [
                'driver' => (string) ($db->DBDriver ?? 'unknown'),
                'database' => $this->redactIdentifier((string) ($db->database ?? '')),
                'latency_ms' => $latencyMs,
            ] : [];

            return $this->check('ok', 'database', 'Conectividade com o banco operacional.', $meta);
        } catch (Throwable $e) {
            log_message('critical', '[Health] Database check failed: ' . $e->getMessage());

            return $this->check('error', 'database', 'Falha de conectividade com o banco.', $detailed ? [
                'hint' => 'Verifique .env, PostgreSQL, extensões pdo_pgsql/pgsql e permissões de rede.',
            ] : []);
        }
    }

    private function checkWritable(bool $detailed = false): array
    {
        $directories = [
            'writable' => WRITEPATH,
            'cache' => WRITEPATH . 'cache',
            'logs' => WRITEPATH . 'logs',
            'session' => WRITEPATH . 'session',
            'uploads' => WRITEPATH . 'uploads',
            'exports' => WRITEPATH . 'exports',
            'installer' => WRITEPATH . 'installer',
        ];

        $results = [];
        $allOk = true;

        foreach ($directories as $label => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $results[$label] = [
                'exists' => $exists,
                'writable' => $writable,
            ];
            if (! $writable) {
                $allOk = false;
            }
        }

        return $this->check(
            $allOk ? 'ok' : 'error',
            'writable',
            $allOk ? 'Diretórios críticos graváveis.' : 'Há diretórios críticos sem permissão de escrita.',
            $detailed ? ['directories' => $results] : []
        );
    }

    private function checkMigrations(bool $detailed = false): array
    {
        $files = $this->migrationFiles();
        if ($files === []) {
            return $this->check('error', 'migrations', 'Nenhuma migration foi encontrada no pacote.', []);
        }

        try {
            $db = $this->db();
            $table = config('Migrations')->table ?? 'migrations';

            if (! $db->tableExists($table)) {
                return $this->check('warning', 'migrations', 'Tabela de migrations ainda não existe.', $detailed ? [
                    'files_count' => count($files),
                    'pending_count' => count($files),
                ] : []);
            }

            $rows = $db->table($table)->select('version, class')->get()->getResultArray();
            $executed = [];
            foreach ($rows as $row) {
                foreach (['version', 'class'] as $field) {
                    $value = (string) ($row[$field] ?? '');
                    if ($value !== '') {
                        $executed[$value] = true;
                    }
                }
            }

            $pending = [];
            foreach ($files as $file => $info) {
                if (! isset($executed[$info['version']]) && ! isset($executed[$info['class']])) {
                    $pending[] = $file;
                }
            }

            $status = empty($pending) ? 'ok' : 'warning';

            return $this->check(
                $status,
                'migrations',
                empty($pending) ? 'Migrations aparentemente sincronizadas.' : 'Há migrations no código que não aparecem como executadas.',
                $detailed ? [
                    'files_count' => count($files),
                    'executed_count' => count($rows),
                    'pending_count' => count($pending),
                    'pending_sample' => array_slice($pending, 0, 12),
                ] : ['pending_count' => count($pending)]
            );
        } catch (Throwable $e) {
            log_message('warning', '[Health] Migrations check failed: ' . $e->getMessage());

            return $this->check('warning', 'migrations', 'Não foi possível validar migrations contra o banco.', $detailed ? [
                'files_count' => count($files),
                'hint' => 'Confirme banco, permissões e tabela de migrations.',
            ] : []);
        }
    }

    private function migrationFiles(): array
    {
        $directory = APPPATH . 'Database/Migrations';
        $files = glob($directory . '/*.php') ?: [];
        $result = [];

        foreach ($files as $path) {
            $base = basename($path, '.php');
            $class = preg_replace('/^\d{4}[-_]\d{2}[-_]\d{2}[-_]\d{4,6}_?/', '', $base) ?: $base;
            $version = str_replace('_', '-', substr($base, 0, 17));
            $result[$base] = [
                'version' => $version,
                'class' => $class,
            ];
        }

        ksort($result);
        return $result;
    }

    private function checkVersion(): array
    {
        $versionFile = FCPATH . 'version.json';
        $meta = ['helper_version' => $this->versionString(false)];

        if (! is_file($versionFile)) {
            return $this->check('warning', 'version', 'Arquivo public/version.json não encontrado.', $meta);
        }

        $decoded = json_decode((string) file_get_contents($versionFile), true);
        if (! is_array($decoded)) {
            return $this->check('warning', 'version', 'Arquivo public/version.json inválido.', $meta);
        }

        $meta['public_version'] = (string) ($decoded['version'] ?? 'unknown');
        $meta['release'] = (string) ($decoded['release'] ?? 'unknown');
        $meta['generated_at'] = (string) ($decoded['generated_at'] ?? ($decoded['updated_at'] ?? 'unknown'));
        $meta['package'] = (string) ($decoded['package'] ?? 'unknown');

        $status = $meta['public_version'] !== 'unknown' ? 'ok' : 'warning';

        return $this->check($status, 'version', 'Metadados de versão disponíveis.', $meta);
    }

    private function versionString(bool $prefix = true): string
    {
        if (function_exists('app_version')) {
            return (string) app_version($prefix);
        }

        $versionFile = FCPATH . 'version.json';
        if (is_file($versionFile)) {
            $decoded = json_decode((string) file_get_contents($versionFile), true);
            if (is_array($decoded) && ! empty($decoded['version'])) {
                return ($prefix ? 'v' : '') . (string) $decoded['version'];
            }
        }

        return $prefix ? 'vunknown' : 'unknown';
    }

    private function checkQueue(bool $detailed = false): array
    {
        try {
            $db = $this->db();
            if (! $db->tableExists('async_jobs')) {
                return $this->check('warning', 'queue', 'Tabela async_jobs ainda não existe.', $detailed ? [
                    'known_queues' => $this->queueConfig->knownQueues,
                ] : []);
            }

            // Batch-fetch all status counts in one GROUP BY query — eliminates N+1 (was 1 query per status)
            $statusRows = $db->table('async_jobs')
                ->select('status, COUNT(*) AS cnt', false)
                ->groupBy('status')
                ->get()
                ->getResultArray();

            $defaultStatuses = ['pending' => 0, 'retrying' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
            $counts = $defaultStatuses;
            foreach ($statusRows as $row) {
                $s = (string) ($row['status'] ?? '');
                if (array_key_exists($s, $counts)) {
                    $counts[$s] = (int) $row['cnt'];
                }
            }

            $threshold = date('Y-m-d H:i:s', time() - (max(1, (int) $this->queueConfig->staleAfterMinutes) * 60));
            $stale = (int) $db->table('async_jobs')
                ->where('status', 'processing')
                ->where('started_at IS NOT NULL', null, false)
                ->where('started_at <=', $threshold)
                ->countAllResults();

            $status = 'ok';
            $message = 'Fila operacional.';
            if ($stale > 0) {
                $status = 'warning';
                $message = 'Há jobs presos em processamento.';
            } elseif (($counts['failed'] ?? 0) > 0) {
                $status = 'warning';
                $message = 'Há jobs com falha a revisar.';
            }

            return $this->check($status, 'queue', $message, $detailed ? [
                'counts' => $counts,
                'stale_processing_count' => $stale,
                'known_queues' => $this->queueConfig->knownQueues,
                'stale_after_minutes' => $this->queueConfig->staleAfterMinutes,
            ] : ['pending' => $counts['pending'] ?? 0, 'processing' => $counts['processing'] ?? 0]);
        } catch (Throwable $e) {
            log_message('warning', '[Health] Queue check failed: ' . $e->getMessage());

            return $this->check('warning', 'queue', 'Não foi possível validar fila de jobs.', $detailed ? [
                'hint' => 'Confirme migration async_jobs e conexão com banco.',
            ] : []);
        }
    }

    private function checkDeepFace(): array
    {
        $circuit = $this->deepFaceCircuitBreaker->state();
        try {
            $result = $this->deepFace->healthCheck();
            $success = (bool) ($result['success'] ?? false);
            $status = $success ? 'ok' : 'warning';

            if (($circuit['state'] ?? 'closed') === 'open') {
                $status = 'warning';
            }

            return $this->check(
                $status,
                'deepface',
                $success ? 'Microserviço DeepFace respondeu.' : 'DeepFace indisponível, degradado ou em fallback.',
                [
                    'service_status' => $result['status'] ?? null,
                    'circuit_breaker' => [
                        'enabled' => (bool) ($circuit['enabled'] ?? false),
                        'state' => (string) ($circuit['state'] ?? 'unknown'),
                        'failure_count' => (int) ($circuit['failure_count'] ?? 0),
                    ],
                ]
            );
        } catch (Throwable $e) {
            log_message('warning', '[Health] DeepFace check failed: ' . $e->getMessage());

            return $this->check('warning', 'deepface', 'Falha ao consultar DeepFace; sistema principal deve seguir operacional.', [
                'circuit_breaker' => [
                    'enabled' => (bool) ($circuit['enabled'] ?? false),
                    'state' => (string) ($circuit['state'] ?? 'unknown'),
                    'failure_count' => (int) ($circuit['failure_count'] ?? 0),
                ],
            ]);
        }
    }


    private function checkStorage(bool $detailed = false): array
    {
        $paths = [
            'uploads' => WRITEPATH . 'uploads',
            'exports' => WRITEPATH . 'exports',
            'cache' => WRITEPATH . 'cache',
            'sessions' => WRITEPATH . 'session',
            'support_bundles' => WRITEPATH . 'support-bundles',
            'observability' => WRITEPATH . 'observability',
        ];

        $results = [];
        $allOk = true;
        foreach ($paths as $label => $path) {
            $exists = is_dir($path);
            if (! $exists && in_array($label, ['support_bundles', 'observability'], true)) {
                @mkdir($path, 0775, true);
                $exists = is_dir($path);
            }
            $writable = $exists && is_writable($path);
            $results[$label] = [
                'exists' => $exists,
                'writable' => $writable,
                'free_mb' => $exists ? (int) floor((@disk_free_space($path) ?: 0) / 1048576) : null,
            ];
            if (! $writable) {
                $allOk = false;
            }
        }

        return $this->check($allOk ? 'ok' : 'error', 'storage', $allOk ? 'Storage operacional.' : 'Storage crítico indisponível ou sem escrita.', $detailed ? $results : []);
    }

    private function checkObservability(bool $detailed = false): array
    {
        $telemetry = new OperationalTelemetryService();
        $health = $telemetry->health();
        $ok = (bool) ($health['writable'] ?? false);

        return $this->check($ok ? 'ok' : 'warning', 'observability', $ok ? 'Telemetria operacional local disponível.' : 'Telemetria operacional local indisponível ou sem escrita.', $detailed ? $health : []);
    }

    private function checkWebsocket(): array
    {
        $port = (int) (getenv('WEBSOCKET_PORT') ?: env('websocket.port', 8080));
        $host = (string) (getenv('WEBSOCKET_HEALTH_HOST') ?: '127.0.0.1');
        $enabled = filter_var(getenv('WEBSOCKET_ENABLED') ?: env('WEBSOCKET_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            return $this->check('warning', 'websocket', 'WebSocket não habilitado neste ambiente.', [
                'enabled' => false,
                'port' => $port,
            ]);
        }

        $started = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.5);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        if (is_resource($socket)) {
            fclose($socket);
            return $this->check('ok', 'websocket', 'Porta WebSocket respondendo.', [
                'enabled' => true,
                'host' => 'local',
                'port' => $port,
                'latency_ms' => $latencyMs,
            ]);
        }

        return $this->check('warning', 'websocket', 'WebSocket habilitado, mas porta não respondeu.', [
            'enabled' => true,
            'host' => 'local',
            'port' => $port,
            'latency_ms' => $latencyMs,
            'hint' => 'Verifique supervisor/systemd do scripts/websocket_server.php.',
        ]);
    }

    private function checkLogs(): array
    {
        $logDir = WRITEPATH . 'logs';
        if (! is_dir($logDir) || ! is_readable($logDir) || ! is_writable($logDir)) {
            return $this->check('error', 'logs', 'Diretório de logs indisponível ou sem permissão.', [
                'directory' => 'writable/logs',
            ]);
        }

        $today = $logDir . DIRECTORY_SEPARATOR . 'log-' . date('Y-m-d') . '.log';
        $latest = $this->latestFile($logDir, '/^log-\d{4}-\d{2}-\d{2}\.log$/');
        $size = is_file($today) ? (int) filesize($today) : 0;
        $tail = $latest ? $this->tail($latest, 250) : [];
        $critical = 0;
        $errors = 0;
        $warnings = 0;

        foreach ($tail as $line) {
            if (stripos($line, 'CRITICAL') !== false) {
                $critical++;
            }
            if (stripos($line, 'ERROR') !== false) {
                $errors++;
            }
            if (stripos($line, 'WARNING') !== false) {
                $warnings++;
            }
        }

        $status = $critical > 0 || $errors > 10 ? 'warning' : 'ok';

        return $this->check($status, 'logs', $status === 'ok' ? 'Logs acessíveis e sem excesso de erros recentes.' : 'Logs recentes indicam erros a revisar.', [
            'directory' => 'writable/logs',
            'today_log_exists' => is_file($today),
            'today_log_size_bytes' => $size,
            'latest_log' => $latest ? basename($latest) : null,
            'recent_critical_count' => $critical,
            'recent_error_count' => $errors,
            'recent_warning_count' => $warnings,
        ]);
    }

    private function checkEnvironment(bool $detailed = false): array
    {
        $issues = [];
        $requiredExtensions = ['json', 'mbstring', 'intl', 'pgsql', 'pdo_pgsql', 'fileinfo', 'zip'];
        foreach ($requiredExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $issues[] = 'missing_extension:' . $extension;
            }
        }

        if (! is_file(ROOTPATH . '.env')) {
            $issues[] = 'missing_env';
        }

        $baseUrl = (string) env('app.baseURL', config('App')->baseURL ?? '');
        if ($baseUrl === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $issues[] = 'invalid_base_url';
        }

        if ((string) date_default_timezone_get() !== 'America/Sao_Paulo') {
            $issues[] = 'timezone_not_america_sao_paulo';
        }

        $status = empty($issues) ? 'ok' : 'warning';

        return $this->check($status, 'env', empty($issues) ? 'Ambiente coerente.' : 'Há ajustes recomendados de ambiente.', $detailed ? [
            'php_version' => PHP_VERSION,
            'environment' => ENVIRONMENT,
            'timezone' => date_default_timezone_get(),
            'issues' => $issues,
        ] : ['issues_count' => count($issues)]);
    }

    private function check(string $status, string $label, string $message, array $meta = []): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'message' => $message,
            'meta' => $this->sanitizeMeta($meta),
            'checked_at' => gmdate(DATE_ATOM),
        ];
    }

    private function publicChecks(array $checks): array
    {
        $public = [];
        foreach ($checks as $key => $check) {
            $public[$key] = [
                'status' => $check['status'] ?? 'error',
                'label' => $check['label'] ?? $key,
                'message' => $check['message'] ?? null,
            ];
        }

        return $public;
    }

    private function isReady(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? 'error') === 'error') {
                return false;
            }
        }

        return true;
    }

    private function buildAlerts(array $checks): array
    {
        $alerts = [];
        foreach ($checks as $key => $check) {
            $status = (string) ($check['status'] ?? 'error');
            if ($status === 'ok') {
                continue;
            }

            $alerts[] = [
                'module' => $key,
                'severity' => $status === 'error' ? 'critical' : 'warning',
                'message' => (string) ($check['message'] ?? ('Anomalia em ' . $key)),
            ];
        }

        return $alerts;
    }

    private function countStatus(array $checks, string $status): int
    {
        $count = 0;
        foreach ($checks as $check) {
            if (($check['status'] ?? null) === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function variant(string $status): string
    {
        return match ($status) {
            'ok', 'healthy', 'ready' => 'success',
            'warning', 'degraded' => 'warning',
            default => 'danger',
        };
    }

    private function latestFile(string $directory, string $pattern): ?string
    {
        $files = @scandir($directory);
        if ($files === false) {
            return null;
        }

        $latest = null;
        $latestTime = 0;
        foreach ($files as $file) {
            if (! preg_match($pattern, $file)) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            $mtime = @filemtime($path) ?: 0;
            if ($mtime >= $latestTime) {
                $latestTime = $mtime;
                $latest = $path;
            }
        }

        return $latest;
    }

    /** @return list<string> */
    private function tail(string $file, int $maxLines): array
    {
        if (! is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return [];
        }

        return array_slice($lines, -1 * max(1, $maxLines));
    }

    private function sanitizeMeta(array $meta): array
    {
        $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'dsn', 'host', 'username', 'user'];
        $sanitized = [];

        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lower, $sensitiveKey)) {
                    $sanitized[$key] = '[redacted]';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMeta($value);
                continue;
            }

            $sanitized[$key] = is_scalar($value) || $value === null ? $value : '[unsupported]';
        }

        return $sanitized;
    }

    private function redactIdentifier(string $value): string
    {
        if ($value === '') {
            return 'unknown';
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 2) . str_repeat('*', max(2, strlen($value) - 4)) . substr($value, -2);
    }
}

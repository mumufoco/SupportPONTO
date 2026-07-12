<?php

namespace App\Services\Backup;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Database as DatabaseConfig;
use Config\ProcessSafety;

/**
 * Database Backup Service
 *
 * Realiza backup automatizado do banco de dados.
 */
class DatabaseBackupService
{
    protected string $backupPath;
    /** @var array<string, mixed> */
    protected array $dbConfig = [];
    protected int $daysToKeep = 30;
    protected string $driver = 'Postgre';
    protected ProcessSafety $processSafety;

    public function __construct(?BaseConnection $connection = null)
    {
        $this->backupPath = WRITEPATH . 'backups/database/';

        if (! is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        $this->processSafety = config(ProcessSafety::class);

        $db = $connection ?? Database::connect();
        $this->driver = (string) ($db->DBDriver ?? 'Postgre');
        $this->dbConfig = $this->resolveConnectionConfig($db);
    }

    public function verifyReadiness(): array
    {
        $checks = [
            'runtime_policy' => $this->verifyRuntimePolicy(),
            'driver' => $this->verifyDriver(),
            'backup_path' => $this->verifyBackupPath(),
            'database_config' => $this->verifyDatabaseConfig(),
            'pg_dump' => $this->verifyBinary('pg_dump'),
            'psql' => $this->verifyBinary('psql'),
            'proc_open' => $this->verifyProcOpenAvailability(),
        ];

        $status = 'ready';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'error') !== 'ok') {
                $status = 'not_ready';
                break;
            }
        }

        return [
            'status' => $status,
            'backup_path' => $this->backupPath,
            'driver' => $this->driver,
            'checks' => $checks,
        ];
    }

    protected function verifyRuntimePolicy(): array
    {
        $allowed = $this->processSafety->canRunDatabaseBackupInCurrentRuntime();

        return [
            'status' => $allowed ? 'ok' : 'error',
            'label' => 'runtime_policy',
            'message' => $allowed
                ? 'Execução de backup permitida para o runtime atual (' . $this->processSafety->currentRuntimeLabel() . ').'
                : 'Backup/restore via shell está bloqueado para o runtime atual (' . $this->processSafety->currentRuntimeLabel() . '). Use a fila CLI jobs:process ou habilite explicitamente o runtime web.',
        ];
    }

    protected function verifyDriver(): array
    {
        return [
            'status' => $this->driver === 'Postgre' ? 'ok' : 'error',
            'label' => 'driver',
            'message' => $this->driver === 'Postgre' ? 'Driver PostgreSQL confirmado.' : 'Driver atual: ' . $this->driver,
        ];
    }

    protected function verifyBackupPath(): array
    {
        $ok = is_dir($this->backupPath) && is_writable($this->backupPath);

        return [
            'status' => $ok ? 'ok' : 'error',
            'label' => 'backup_path',
            'message' => $ok ? $this->backupPath : 'Diretório de backup ausente ou não gravável: ' . $this->backupPath,
        ];
    }

    protected function verifyDatabaseConfig(): array
    {
        foreach (['hostname', 'username', 'database'] as $key) {
            if (empty($this->dbConfig[$key])) {
                return [
                    'status' => 'error',
                    'label' => 'database_config',
                    'message' => 'Configuração obrigatória ausente: ' . $key,
                ];
            }
        }

        return [
            'status' => 'ok',
            'label' => 'database_config',
            'message' => sprintf(
                '%s@%s:%s/%s',
                (string) $this->dbConfig['username'],
                (string) $this->dbConfig['hostname'],
                (string) ($this->dbConfig['port'] ?? 5432),
                (string) $this->dbConfig['database']
            ),
        ];
    }

    protected function verifyBinary(string $binary): array
    {
        if (! $this->processSafety->canDiscoverBinariesInCurrentRuntime()) {
            return [
                'status' => is_cli() ? 'error' : 'warning',
                'label' => $binary,
                'message' => is_cli()
                    ? 'Descoberta de binário via shell indisponível no runtime CLI atual.'
                    : 'Descoberta de binário via shell desabilitada no runtime web por política de segurança.',
            ];
        }

        if (! $this->isShellExecutionAvailable()) {
            return [
                'status' => 'error',
                'label' => $binary,
                'message' => 'Funções de shell estão desabilitadas no ambiente PHP.',
            ];
        }

        $path = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));

        return [
            'status' => $path !== '' ? 'ok' : 'error',
            'label' => $binary,
            'message' => $path !== '' ? $path : 'Binário não encontrado no PATH.',
        ];
    }

    protected function verifyProcOpenAvailability(): array
    {
        $ok = function_exists('proc_open') && ! $this->isFunctionDisabled('proc_open');

        return [
            'status' => $ok ? 'ok' : 'error',
            'label' => 'proc_open',
            'message' => $ok ? 'proc_open disponível.' : 'proc_open indisponível para executar backup/restore.',
        ];
    }

    public function createBackup(): array
    {
        $this->assertBackupExecutionAllowed();

        $timestamp = date('Y-m-d_H-i-s');
        $this->assertPostgreDriver();
        $filename = 'backup_' . $this->dbConfig['database'] . '_' . $timestamp . '.dump';
        $filepath = $this->backupPath . $filename;
        $gzipPath = $filepath . '.gz';

        try {
            $command = $this->buildBackupCommand($filepath);
            $this->runCommand($command, (string) ($this->dbConfig['password'] ?? ''));

            if (! file_exists($filepath) || filesize($filepath) === 0) {
                throw new \RuntimeException('Arquivo de backup vazio ou não criado.');
            }

            $originalSize = (int) filesize($filepath);
            $this->compressFile($filepath, $gzipPath);
            unlink($filepath);
            $compressedSize = (int) filesize($gzipPath);

            $result = [
                'success' => true,
                'filename' => basename($gzipPath),
                'filepath' => $gzipPath,
                'size' => $compressedSize,
                'original_size' => $originalSize,
                'compression_ratio' => $originalSize > 0 ? round((1 - ($compressedSize / $originalSize)) * 100, 2) : 0.0,
                'timestamp' => $timestamp,
                'driver' => $this->driver,
            ];

            log_message('info', 'Backup criado com sucesso: {file}', ['file' => basename($gzipPath)]);
            $this->sendNotification($result);

            return $result;
        } catch (\Throwable $e) {
            if (is_file($filepath)) {
                @unlink($filepath);
            }

            $error = [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => $timestamp,
                'driver' => $this->driver,
            ];

            log_message('error', 'Falha no backup: {message}', ['message' => $e->getMessage()]);
            $this->sendNotification($error);

            return $error;
        }
    }

    protected function compressFile(string $source, string $dest): void
    {
        $bufferSize = 4096;
        $file = fopen($source, 'rb');
        $zipped = gzopen($dest, 'wb9');

        if ($file === false || $zipped === false) {
            throw new \RuntimeException('Falha ao comprimir arquivo de backup.');
        }

        while (! feof($file)) {
            $chunk = fread($file, $bufferSize);
            if ($chunk === false) {
                fclose($file);
                gzclose($zipped);
                throw new \RuntimeException('Falha ao ler arquivo durante compressão.');
            }

            gzwrite($zipped, $chunk);
        }

        fclose($file);
        gzclose($zipped);
    }

    public function cleanOldBackups(): int
    {
        $deleted = 0;
        $cutoffDate = Time::now()->subDays($this->daysToKeep);

        $files = glob($this->backupPath . 'backup_*.sql.gz');
        $files = array_merge($files ?: [], glob($this->backupPath . 'backup_*.dump.gz') ?: []);

        foreach ($files as $file) {
            $fileDate = filemtime($file);

            if ($fileDate < $cutoffDate->getTimestamp() && unlink($file)) {
                $deleted++;
                log_message('info', 'Backup antigo removido: {file}', ['file' => basename($file)]);
            }
        }

        return $deleted;
    }

    /** @return list<array<string, mixed>> */
    public function listBackups(): array
    {
        $backups = [];
        $files = glob($this->backupPath . 'backup_*.sql.gz');
        $files = array_merge($files ?: [], glob($this->backupPath . 'backup_*.dump.gz') ?: []);

        usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach ($files as $file) {
            $size = (int) filesize($file);
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'date' => date('Y-m-d H:i:s', (int) filemtime($file)),
                'age_days' => (int) floor((time() - (int) filemtime($file)) / 86400),
            ];
        }

        return $backups;
    }

    public function restoreBackup(string $backupFile): array
    {
        try {
            $this->assertBackupExecutionAllowed();

            if (! file_exists($backupFile)) {
                throw new \RuntimeException('Arquivo de backup não encontrado.');
            }

            $restoredFile = preg_replace('/\.gz$/', '', $backupFile) ?: $backupFile;
            $this->decompressFile($backupFile, $restoredFile);

            $command = $this->buildRestoreCommand($restoredFile);
            $this->runCommand($command, (string) ($this->dbConfig['password'] ?? ''));

            if (is_file($restoredFile)) {
                unlink($restoredFile);
            }

            $result = [
                'success' => true,
                'filename' => basename($backupFile),
                'message' => 'Backup restaurado com sucesso',
                'driver' => $this->driver,
            ];

            log_message('info', 'Backup restaurado: {file}', ['file' => basename($backupFile)]);

            return $result;
        } catch (\Throwable $e) {
            $error = [
                'success' => false,
                'error' => $e->getMessage(),
                'driver' => $this->driver,
            ];

            log_message('error', 'Falha ao restaurar backup: {message}', ['message' => $e->getMessage()]);

            return $error;
        }
    }

    protected function decompressFile(string $source, string $dest): void
    {
        $bufferSize = 4096;
        $file = gzopen($source, 'rb');
        $outFile = fopen($dest, 'wb');

        if ($file === false || $outFile === false) {
            throw new \RuntimeException('Falha ao descomprimir arquivo de backup.');
        }

        while (! gzeof($file)) {
            $chunk = gzread($file, $bufferSize);
            if ($chunk === false) {
                gzclose($file);
                fclose($outFile);
                throw new \RuntimeException('Falha ao ler arquivo compactado.');
            }

            fwrite($outFile, $chunk);
        }

        gzclose($file);
        fclose($outFile);
    }

    /**
     * Executa pg_dump/psql sem expor PGPASSWORD no `ps aux`.
     *
     * @throws \RuntimeException
     */
    protected function runCommand(string $command, string $password): void
    {
        if (! $this->processSafety->canRunDatabaseBackupInCurrentRuntime()) {
            throw new \RuntimeException('Execução de backup/restore via shell bloqueada para o runtime atual. Utilize a fila CLI jobs:process ou habilite explicitamente o runtime web.');
        }

        if (! function_exists('proc_open') || $this->isFunctionDisabled('proc_open')) {
            throw new \RuntimeException('proc_open está indisponível no ambiente PHP.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $existingEnv = getenv();
        $env = is_array($existingEnv) ? $existingEnv : [];
        $env['PGPASSWORD'] = $password;

        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (! is_resource($process)) {
            throw new \RuntimeException('Falha ao iniciar processo de backup/restore.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            $output = trim($stderr !== '' ? $stderr : $stdout);
            throw new \RuntimeException('Comando falhou (código ' . $returnCode . '): ' . $output);
        }
    }

    protected function buildBackupCommand(string $filepath): string
    {
        $this->assertPostgreDriver();

        return sprintf(
            'pg_dump --host=%s --port=%s --username=%s --format=p --no-owner --no-privileges %s > %s',
            escapeshellarg((string) $this->dbConfig['hostname']),
            escapeshellarg((string) ($this->dbConfig['port'] ?? 5432)),
            escapeshellarg((string) $this->dbConfig['username']),
            escapeshellarg((string) $this->dbConfig['database']),
            escapeshellarg($filepath)
        );
    }

    protected function buildRestoreCommand(string $restoreFile): string
    {
        $this->assertPostgreDriver();

        return sprintf(
            'psql --host=%s --port=%s --username=%s --dbname=%s --file=%s',
            escapeshellarg((string) $this->dbConfig['hostname']),
            escapeshellarg((string) ($this->dbConfig['port'] ?? 5432)),
            escapeshellarg((string) $this->dbConfig['username']),
            escapeshellarg((string) $this->dbConfig['database']),
            escapeshellarg($restoreFile)
        );
    }

    protected function assertBackupExecutionAllowed(): void
    {
        if (! $this->processSafety->canRunDatabaseBackupInCurrentRuntime()) {
            throw new \RuntimeException('Execução de backup/restore via shell bloqueada para o runtime atual. Utilize a fila CLI jobs:process ou habilite explicitamente o runtime web.');
        }
    }

    protected function assertPostgreDriver(): void
    {
        if ($this->driver !== 'Postgre') {
            throw new \RuntimeException('O backup automático desta aplicação suporta apenas PostgreSQL.');
        }
    }

    protected function sendNotification(array $payload): void
    {
        // Intencionalmente mantido como stub controlado para não interromper o fluxo de backup.
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, 2, ',', '.') . ' ' . $units[$unit];
    }

    /** @return array<string, mixed> */
    protected function resolveConnectionConfig(BaseConnection $db): array
    {
        $config = new DatabaseConfig();
        $group = $config->defaultGroup ?? 'default';
        $groupConfig = $config->{$group} ?? [];
        if (! is_array($groupConfig)) {
            $groupConfig = [];
        }

        return [
            'hostname' => (string) ($db->hostname ?? $groupConfig['hostname'] ?? ''),
            'username' => (string) ($db->username ?? $groupConfig['username'] ?? ''),
            'password' => (string) ($db->password ?? $groupConfig['password'] ?? ''),
            'database' => (string) ($db->database ?? $groupConfig['database'] ?? ''),
            'port' => (int) ($db->port ?? $groupConfig['port'] ?? 5432),
            'DBDriver' => (string) ($db->DBDriver ?? $groupConfig['DBDriver'] ?? 'Postgre'),
        ];
    }

    protected function isShellExecutionAvailable(): bool
    {
        return function_exists('shell_exec') && ! $this->isFunctionDisabled('shell_exec');
    }

    protected function isFunctionDisabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array($function, $disabled, true);
    }
}

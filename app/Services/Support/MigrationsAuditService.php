<?php

declare(strict_types=1);

namespace App\Services\Support;

use Config\Database;
use Config\Migrations as MigrationsConfig;
use Throwable;

class MigrationsAuditService
{
    public function build(bool $withConnections = true): array
    {
        $files = $this->collectMigrationFiles();
        $config = config('Migrations');
        $regex = $this->timestampRegex($config instanceof MigrationsConfig ? $config->timestampFormat : 'Y-m-d-His_');

        $invalid = [];
        $duplicatePrefixes = [];
        $prefixMap = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (! preg_match($regex, $basename)) {
                $invalid[] = $basename;
            }

            $prefix = explode('_', $basename, 2)[0] ?? $basename;
            $prefixMap[$prefix] ??= [];
            $prefixMap[$prefix][] = $basename;
        }

        foreach ($prefixMap as $prefix => $items) {
            if (count($items) > 1) {
                $duplicatePrefixes[$prefix] = $items;
            }
        }

        $database = $this->databaseStatus($withConnections, count($files));
        $status = 'ok';
        if ($invalid !== [] || $duplicatePrefixes !== [] || in_array(($database['status'] ?? 'ok'), ['blocker'], true)) {
            $status = 'blocker';
        } elseif (($database['status'] ?? 'ok') === 'warning') {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'generated_at' => date(DATE_ATOM),
            'summary' => [
                'migration_files' => count($files),
                'invalid_filenames' => count($invalid),
                'duplicate_prefixes' => count($duplicatePrefixes),
                'database_status' => $database['status'] ?? 'unknown',
            ],
            'naming' => [
                'timestamp_format' => $config->timestampFormat ?? 'unknown',
                'regex' => $regex,
                'invalid_files' => $invalid,
                'duplicate_prefixes' => $duplicatePrefixes,
            ],
            'database' => $database,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectMigrationFiles(): array
    {
        $directory = APPPATH . 'Database/Migrations';
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        return array_values(array_filter($files, static fn ($file): bool => is_file($file)));
    }

    private function timestampRegex(string $timestampFormat): string
    {
        return match ($timestampFormat) {
            'YmdHis_' => '/^\d{14}_[A-Za-z0-9_]+\.php$/',
            'Y_m_d_His_' => '/^\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+\.php$/',
            default => '/^\d{4}-\d{2}-\d{2}-\d{6}_[A-Za-z0-9_]+\.php$/',
        };
    }

    private function databaseStatus(bool $withConnections, int $expectedFiles): array
    {
        if (! $withConnections) {
            return [
                'status' => 'warning',
                'details' => 'Auditoria do banco ignorada por parâmetro.',
                'applied_count' => null,
                'pending_count' => null,
                'table_exists' => null,
            ];
        }

        try {
            $db = Database::connect();
            $migrationsTable = config('Migrations')->table ?? 'migrations';
            $tableExists = $db->tableExists($migrationsTable);
            if (! $tableExists) {
                return [
                    'status' => 'warning',
                    'details' => sprintf('Tabela de controle de migrations "%s" ainda não existe.', $migrationsTable),
                    'applied_count' => 0,
                    'pending_count' => $expectedFiles,
                    'table_exists' => false,
                ];
            }

            $appliedCount = (int) $db->table($migrationsTable)->countAllResults();
            $pending = max($expectedFiles - $appliedCount, 0);

            return [
                'status' => 'ok',
                'details' => sprintf('Tabela "%s" acessível.', $migrationsTable),
                'applied_count' => $appliedCount,
                'pending_count' => $pending,
                'table_exists' => true,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'blocker',
                'details' => 'Falha ao inspecionar o estado das migrations no banco.',
                'error' => $e->getMessage(),
                'applied_count' => null,
                'pending_count' => null,
                'table_exists' => null,
            ];
        }
    }
}

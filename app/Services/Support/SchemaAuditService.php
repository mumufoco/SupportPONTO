<?php

declare(strict_types=1);

namespace App\Services\Support;

use Config\Database;
use Throwable;

class SchemaAuditService
{
    private const REQUIRED_AUTH_TABLES = [
        'users',
        'auth_groups',
        'auth_permissions',
        'auth_groups_permissions',
        'auth_identities',
        'auth_logins',
        'auth_remember_tokens',
        'auth_token_logins',
    ];

    private const CRITICAL_TABLES = [
        'settings',
        'employees',
        'time_punches',
        'justifications',
        'warnings',
        'audit_logs',
        'biometric_templates',
        'companies',
        'departments',
        'positions',
        'roles',
        'work_units',
        'work_shifts',
        'schedules',
        'oauth_access_tokens',
        'oauth_refresh_tokens',
        'notifications',
        'report_queue',
        'data_exports',
        'timesheet_consolidated',
        'push_notification_tokens',
        'push_subscriptions',
        'geofences',
        'user_consents',
        'facial_recognition_logs',
        'pending_punches',
        'async_jobs',
        'audit_chain_anchors',
        'biometric_encryption_audit',
        'chat_rooms',
        'chat_room_members',
        'chat_messages',
        'chat_message_reactions',
        'chat_online_users',
        'nsr_counter',
        'qrcode_used_tokens',
    ];

    private const MINIMUM_COLUMNS = [
        'settings' => ['id', 'group', 'key', 'value'],
        'employees' => ['id', 'name', 'email'],
        'time_punches' => ['id', 'employee_id'],
        'justifications' => ['id', 'employee_id'],
        'warnings' => ['id', 'employee_id'],
        'audit_logs' => ['id', 'user_id'],
        'biometric_templates' => ['id', 'employee_id'],
        'companies' => ['id', 'name'],
        'departments' => ['id', 'name'],
        'positions' => ['id', 'name'],
        'roles' => ['id', 'name'],
        'work_units' => ['id', 'name'],
        'work_shifts' => ['id', 'name'],
        'schedules' => ['id', 'employee_id'],
        'oauth_access_tokens' => ['id', 'employee_id'],
        'oauth_refresh_tokens' => ['id', 'access_token_id'],
        'notifications' => ['id', 'employee_id'],
        'report_queue' => ['id', 'requested_by'],
        'data_exports' => ['id', 'requested_by'],
        'timesheet_consolidated' => ['id', 'employee_id'],
        'push_notification_tokens' => ['id', 'employee_id'],
        'push_subscriptions' => ['id', 'employee_id'],
        'geofences' => ['id', 'employee_id'],
        'user_consents' => ['id', 'employee_id'],
        'facial_recognition_logs' => ['id', 'employee_id'],
        'pending_punches' => ['id', 'employee_id'],
        'async_jobs' => ['id', 'type'],
        'audit_chain_anchors' => ['id', 'anchor_hash'],
        'biometric_encryption_audit' => ['id', 'employee_id'],
        'chat_rooms' => ['id', 'name'],
        'chat_room_members' => ['id', 'room_id', 'employee_id'],
        'chat_messages' => ['id', 'room_id', 'employee_id'],
        'chat_message_reactions' => ['id', 'message_id', 'employee_id'],
        'chat_online_users' => ['id', 'employee_id'],
        'nsr_counter' => ['id', 'current_value'],
        'qrcode_used_tokens' => ['id', 'token_hash'],
        'users' => ['id'],
        'auth_groups' => ['id', 'name'],
        'auth_permissions' => ['id', 'name'],
        'auth_groups_permissions' => ['group', 'permission'],
        'auth_identities' => ['id', 'user_id'],
        'auth_logins' => ['id'],
        'auth_remember_tokens' => ['id', 'user_id'],
        'auth_token_logins' => ['id', 'user_id'],
    ];

    public function build(bool $withConnections = true): array
    {
        $migrationsAudit = (new MigrationsAuditService())->build($withConnections);
        $expectedAppTables = $this->discoverExpectedAppTables();
        $expectedTables = array_values(array_unique(array_merge($expectedAppTables, self::REQUIRED_AUTH_TABLES)));
        sort($expectedTables);

        $database = $this->databaseAudit($withConnections, $expectedTables);
        $status = $this->aggregateStatus($migrationsAudit, $database);

        return [
            'status' => $status,
            'generated_at' => date(DATE_ATOM),
            'summary' => [
                'expected_tables' => count($expectedTables),
                'critical_tables' => count($database['critical_tables'] ?? []),
                'missing_tables' => count($database['missing_tables'] ?? []),
                'missing_critical_tables' => count($database['missing_critical_tables'] ?? []),
                'column_issues' => count($database['column_issues'] ?? []),
                'database_status' => $database['status'] ?? 'unknown',
                'migrations_status' => $migrationsAudit['status'] ?? 'unknown',
            ],
            'expected' => [
                'tables' => $expectedTables,
                'critical_tables' => array_values(array_unique(array_merge($expectedAppTables, self::REQUIRED_AUTH_TABLES, self::CRITICAL_TABLES))),
            ],
            'migrations' => $migrationsAudit,
            'database' => $database,
        ];
    }

    /**
     * @return list<string>
     */
    private function discoverExpectedAppTables(): array
    {
        $directory = APPPATH . 'Database/Migrations';
        if (! is_dir($directory)) {
            return self::CRITICAL_TABLES;
        }

        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        $tables = [];
        foreach ($files as $file) {
            $content = (string) @file_get_contents($file);
            if ($content === '') {
                continue;
            }

            if (preg_match_all('/->createTable\([\"\']([^\"\']+)[\"\']/', $content, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[] = (string) $table;
                }
            }
        }

        $tables = array_values(array_unique(array_merge($tables, self::CRITICAL_TABLES)));
        sort($tables);

        return $tables;
    }

    /**
     * @param list<string> $expectedTables
     */
    private function databaseAudit(bool $withConnections, array $expectedTables): array
    {
        $criticalTables = array_values(array_unique(array_merge($expectedTables, self::REQUIRED_AUTH_TABLES, self::CRITICAL_TABLES)));
        sort($criticalTables);

        if (! $withConnections) {
            return [
                'status' => 'warning',
                'details' => 'Auditoria do banco ignorada por parâmetro.',
                'critical_tables' => $criticalTables,
                'existing_tables' => [],
                'missing_tables' => [],
                'missing_critical_tables' => [],
                'column_issues' => [],
                'migration_records' => null,
            ];
        }

        try {
            $db = Database::connect();
            $existingTables = array_map('strval', $db->listTables());
            sort($existingTables);

            $existingLookup = array_fill_keys($existingTables, true);
            $missingTables = [];
            foreach ($expectedTables as $table) {
                if (! isset($existingLookup[$table])) {
                    $missingTables[] = $table;
                }
            }

            $missingCritical = [];
            foreach ($criticalTables as $table) {
                if (! isset($existingLookup[$table])) {
                    $missingCritical[] = $table;
                }
            }

            $columnIssues = [];
            foreach (self::MINIMUM_COLUMNS as $table => $requiredColumns) {
                if (! isset($existingLookup[$table])) {
                    continue;
                }

                $existingColumns = array_map('strval', $db->getFieldNames($table));
                $missingColumns = array_values(array_diff($requiredColumns, $existingColumns));
                if ($missingColumns !== []) {
                    $columnIssues[$table] = $missingColumns;
                }
            }

            $migrationsTable = config('Migrations')->table ?? 'migrations';
            $migrationRecords = null;
            if ($db->tableExists($migrationsTable)) {
                $migrationRecords = (int) $db->table($migrationsTable)->countAllResults();
            }

            $status = 'ok';
            if ($missingCritical !== []) {
                $status = 'blocker';
            } elseif ($missingTables !== [] || $columnIssues !== []) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'details' => 'Auditoria de schema concluída.',
                'critical_tables' => $criticalTables,
                'existing_tables' => $existingTables,
                'missing_tables' => $missingTables,
                'missing_critical_tables' => $missingCritical,
                'column_issues' => $columnIssues,
                'migration_records' => $migrationRecords,
                'migrations_table' => $migrationsTable,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'blocker',
                'details' => 'Falha ao auditar o schema do banco.',
                'error' => $e->getMessage(),
                'critical_tables' => $criticalTables,
                'existing_tables' => [],
                'missing_tables' => [],
                'missing_critical_tables' => $criticalTables,
                'column_issues' => [],
                'migration_records' => null,
            ];
        }
    }

    private function aggregateStatus(array $migrationsAudit, array $database): string
    {
        if (($migrationsAudit['status'] ?? 'ok') === 'blocker' || ($database['status'] ?? 'ok') === 'blocker') {
            return 'blocker';
        }

        if (($migrationsAudit['status'] ?? 'ok') === 'warning' || ($database['status'] ?? 'ok') === 'warning') {
            return 'warning';
        }

        return 'ok';
    }
}

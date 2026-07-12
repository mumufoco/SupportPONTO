<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = __DIR__ . '/../Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * PostgreSQL is the official and only supported production database driver for this project.
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'postgres',
        'password'     => '',
        'database'     => 'supportponto',
        'DBDriver'     => 'Postgre',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 5432,
        'numberNative' => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * Dedicated test database connection.
     */
    public array $tests = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'postgres',
        'password'     => 'postgres',
        'database'     => 'supportponto_test',
        'DBDriver'     => 'Postgre',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 5432,
        'numberNative' => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->hydrateGroupFromEnvironment($this->default, 'database.default', [
            'hostname' => getenv('PGHOST') ?: getenv('DB_HOST') ?: 'localhost',
            'username' => getenv('PGUSER') ?: getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('PGPASSWORD') ?: getenv('DB_PASSWORD') ?: '',
            'database' => getenv('PGDATABASE') ?: getenv('DB_DATABASE') ?: 'supportponto',
            'DBDriver' => getenv('database.default.DBDriver') ?: 'Postgre',
            'port'     => (int) (getenv('PGPORT') ?: getenv('DB_PORT') ?: 5432),
            'charset'  => 'utf8',
            'DBCollat' => '',
        ]);

        $this->hydrateGroupFromEnvironment($this->tests, 'database.tests', [
            'hostname' => getenv('database.tests.hostname') ?: '127.0.0.1',
            'username' => getenv('database.tests.username') ?: 'postgres',
            'password' => getenv('database.tests.password') ?: 'postgres',
            'database' => getenv('database.tests.database') ?: 'supportponto_test',
            'DBDriver' => getenv('database.tests.DBDriver') ?: 'Postgre',
            'port'     => (int) (getenv('database.tests.port') ?: 5432),
            'charset'  => 'utf8',
            'DBCollat' => '',
        ]);

        if (defined('ENVIRONMENT')) {
            $this->default['DBDebug'] = ENVIRONMENT !== 'production';
            $this->tests['DBDebug'] = true;
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }

        $this->normalizeDriverConfig($this->default);
        $this->normalizeDriverConfig($this->tests);
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $fallbacks
     */
    private function hydrateGroupFromEnvironment(array &$group, string $prefix, array $fallbacks): void
    {
        foreach ($fallbacks as $key => $fallback) {
            $value = getenv($prefix . '.' . $key);
            if ($value === false || $value === '') {
                $value = $fallback;
            }

            if ($key === 'port') {
                $group[$key] = (int) $value;
                continue;
            }

            $group[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $group
     */
    private function normalizeDriverConfig(array &$group): void
    {
        $driver = (string) ($group['DBDriver'] ?? 'Postgre');

        if ($driver === 'Postgre') {
            $group['charset'] = 'utf8';
            $group['DBCollat'] = '';
            $group['port'] = (int) ($group['port'] ?? 5432);
            return;
        }

        if ($driver === 'SQLite3') {
            $group['charset'] = '';
            $group['DBCollat'] = '';
            return;
        }

        if (in_array($driver, ['MySQLi', 'SQLSRV'], true)) {
            $group['charset'] = 'utf8mb4';
            $group['DBCollat'] = 'utf8mb4_general_ci';
        }
    }
}

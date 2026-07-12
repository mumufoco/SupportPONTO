<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fills historical migration version gap (2026-01-01-120000)
 * so rollback/migrate operations can proceed when this version
 * exists in the migrations table from older deployments.
 */
class FillMigrationGap extends Migration
{
    public function up()
    {
        // Intentionally left blank.
    }

    public function down()
    {
        // Intentionally left blank.
    }
}

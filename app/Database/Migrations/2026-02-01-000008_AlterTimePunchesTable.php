<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterTimePunchesTable extends Migration
{
    public function up()
    {
        // Migração histórica de alteração. A criação da tabela foi consolidada em
        // 2026-02-01-0037_create_time_punches_table.php.
        // Mantemos esta etapa como no-op para evitar recriação/colisão em instalações novas.
    }

    public function down()
    {
        // Sem reversão: a estrutura canônica da tabela é gerenciada pela migração 0037.
    }
}

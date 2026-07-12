<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pacote 433 — compatibilidade histórica.
 *
 * A tabela timesheet_consolidated já é criada pela migration 0013, que roda
 * antes desta. A versão antiga desta migration tentava criar a mesma tabela de
 * novo e ainda declarava FK para justifications antes da tabela existir, o que
 * quebrava instalação limpa. As FKs/índices complementares são aplicados pela
 * migration 0433, depois que todas as tabelas base existem.
 */
class CreateTimesheetConsolidatedTable extends Migration
{
    public function up()
    {
        log_message('info', '[Package433] Migration 0027 mantida como no-op de compatibilidade; schema canônico tratado por 0013 + 0433.');
    }

    public function down()
    {
        // No-op intencional: não remove tabela compartilhada por migrations mais antigas.
    }
}

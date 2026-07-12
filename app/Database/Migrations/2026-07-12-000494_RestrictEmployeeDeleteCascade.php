<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Troca ON DELETE CASCADE por ON DELETE RESTRICT nas tabelas que guardam registro legal
 * de ponto e biometria, vinculadas a employees(id).
 *
 * Auditoria CRIT-08: EmployeeModel não tinha soft-delete habilitado, então
 * EmployeeStatusService::deleteEmployee()/rejectRegistration() disparavam um DELETE
 * físico real em `employees`. Com CASCADE, isso arrastava silenciosamente todo o
 * histórico de time_punches, justifications, warnings e biometric_templates do
 * funcionário — violando a obrigação de retenção imutável de registros de ponto
 * (Portaria MTE 671/2021) e sem qualquer possibilidade de recuperação.
 *
 * A correção principal é a nível de aplicação (EmployeeModel::$useSoftDeletes = true,
 * ver migração 2026-02-01-000038 que já criava a coluna deleted_at nunca usada) — esta
 * migração é a segunda camada de defesa: mesmo que algum código futuro (ou uma consulta
 * manual direta no banco) tente excluir fisicamente um funcionário com histórico
 * relacionado, o banco agora recusa a operação em vez de apagar os registros em cascata.
 */
class RestrictEmployeeDeleteCascade extends Migration
{
    /** @var array<int,string> */
    private array $tables = ['time_punches', 'justifications', 'warnings', 'biometric_templates'];

    public function up()
    {
        foreach ($this->tables as $table) {
            $constraint = "{$table}_employee_id_foreign";
            $this->db->query("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint};");
            $this->db->query(
                "ALTER TABLE {$table}
                    ADD CONSTRAINT {$constraint}
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                    ON UPDATE CASCADE ON DELETE RESTRICT;"
            );
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            $constraint = "{$table}_employee_id_foreign";
            $this->db->query("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint};");
            $this->db->query(
                "ALTER TABLE {$table}
                    ADD CONSTRAINT {$constraint}
                    FOREIGN KEY (employee_id) REFERENCES employees(id)
                    ON UPDATE CASCADE ON DELETE CASCADE;"
            );
        }
    }
}

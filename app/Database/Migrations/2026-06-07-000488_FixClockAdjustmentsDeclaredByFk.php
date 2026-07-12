<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrige a chave estrangeira de `clock_adjustments.declared_by`.
 *
 * A migração anterior (2026-06-07-000487) apontou esse campo para `users(id)` por engano —
 * seguindo a convenção textual de outras tabelas do sistema. Mas o SupportPONTO NÃO autentica
 * administradores via a tabela `users` do CodeIgniter Shield (que está vazia em produção); o
 * "usuário logado" é resolvido a partir de `employees` (ver
 * App\Traits\ControllerSessionContextTrait::loadCurrentUser(), que usa EmployeeModel::find()
 * a partir do user_id da sessão). Ou seja, `$this->currentUser->id` em qualquer controller
 * (incluindo Admin\ClockAdjustmentController) é, na prática, um `employees.id`.
 *
 * Sem esta correção, toda tentativa de declarar um ajuste de relógio falharia em produção com
 * violação de chave estrangeira (nenhuma linha existe em `users`).
 */
class FixClockAdjustmentsDeclaredByFk extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE clock_adjustments DROP CONSTRAINT IF EXISTS clock_adjustments_declared_by_foreign;');
        $this->db->query(
            'ALTER TABLE clock_adjustments
                ADD CONSTRAINT clock_adjustments_declared_by_foreign
                FOREIGN KEY (declared_by) REFERENCES employees(id)
                ON UPDATE CASCADE ON DELETE RESTRICT;'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE clock_adjustments DROP CONSTRAINT IF EXISTS clock_adjustments_declared_by_foreign;');
        $this->db->query(
            'ALTER TABLE clock_adjustments
                ADD CONSTRAINT clock_adjustments_declared_by_foreign
                FOREIGN KEY (declared_by) REFERENCES users(id)
                ON UPDATE CASCADE ON DELETE RESTRICT;'
        );
    }
}

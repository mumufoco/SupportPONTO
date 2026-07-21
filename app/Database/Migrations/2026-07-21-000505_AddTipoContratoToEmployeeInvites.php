<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Permite que o admin pré-defina o tipo de contrato no convite, no mesmo
 * espírito do campo `role` já existente -- ambos são travados no formulário
 * de autocadastro (EmployeeInviteController::store() força os dois a partir
 * do convite, o colaborador não escolhe nenhum dos dois).
 */
class AddTipoContratoToEmployeeInvites extends Migration
{
    private string $table = 'employee_invites';
    private string $column = 'tipo_contrato';

    public function up(): void
    {
        if (!$this->db->tableExists($this->table)) {
            return;
        }

        if (!in_array($this->column, $this->db->getFieldNames($this->table), true)) {
            $this->forge->addColumn($this->table, [
                $this->column => [
                    'type'       => 'VARCHAR',
                    'constraint' => '100',
                    'null'       => true,
                    'after'      => 'role',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists($this->table) && in_array($this->column, $this->db->getFieldNames($this->table), true)) {
            $this->forge->dropColumn($this->table, $this->column);
        }
    }
}

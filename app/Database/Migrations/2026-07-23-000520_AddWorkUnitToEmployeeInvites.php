<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Permite que o admin já pré-defina a Unidade no convite, no mesmo espírito
 * de `department`/`position` já existentes -- o colaborador vê essa unidade
 * pré-selecionada ao preencher o autocadastro (não é travado como `role`/
 * `tipo_contrato`, pode ajustar antes de enviar, mesmo comportamento já
 * aplicado a Departamento/Cargo).
 */
class AddWorkUnitToEmployeeInvites extends Migration
{
    private string $table = 'employee_invites';
    private string $column = 'work_unit';

    public function up(): void
    {
        if (!$this->db->tableExists($this->table)) {
            return;
        }

        if (!in_array($this->column, $this->db->getFieldNames($this->table), true)) {
            $this->forge->addColumn($this->table, [
                $this->column => [
                    'type'       => 'VARCHAR',
                    'constraint' => '150',
                    'null'       => true,
                    'after'      => 'department',
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

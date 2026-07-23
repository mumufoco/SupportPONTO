<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `possui_ctps_fisica` -- toggle Sim/Nao que decide se ctps_numero/serie/uf/
 * data_emissao (carteira de trabalho FISICA, o livrinho de papel) sao
 * obrigatorios (ver App\Validation\CustomRules::required_if_true()), mesmo
 * padrao de possui_cnh (2026-07-21-000512_AddCnhFieldsToEmployees).
 *
 * Por que precisa existir: desde a CTPS Digital (2019), a maioria dos novos
 * admitidos NUNCA teve carteira fisica -- o vinculo no eSocial/CAGED e feito
 * so por CPF. Exigir numero/serie da CTPS fisica de todo mundo (como o
 * cadastro fazia antes desta migracao) bloqueava o cadastro de qualquer
 * colaborador que só tenha CTPS Digital, mesmo sendo esse o caso mais comum
 * hoje. Default false: assume CTPS Digital (sem numero/serie) até o RH marcar
 * que o colaborador tem, de fato, a carteira física antiga.
 */
class AddCtpsDigitalFlagToEmployees extends Migration
{
    private string $table = 'employees';

    public function up(): void
    {
        if (! $this->db->fieldExists('possui_ctps_fisica', $this->table)) {
            $this->forge->addColumn($this->table, [
                'possui_ctps_fisica' => ['type' => 'BOOLEAN', 'null' => false, 'default' => false],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('possui_ctps_fisica', $this->table)) {
            $this->forge->dropColumn($this->table, 'possui_ctps_fisica');
        }
    }
}

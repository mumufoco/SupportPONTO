<?php

declare(strict_types=1);

namespace App\Services\Employees\Management;

/**
 * Última barreira antes de um cadastro de colaborador virar "ativo": confere se
 * os campos obrigatórios por lei (Portaria MTE 671/2021 / eSocial) realmente
 * chegaram preenchidos, não importa por qual caminho o registro foi criado
 * (cadastro tradicional, convite, autocadastro...).
 *
 * `EmployeeValidationRulesProvider`/`RegisterPolicyService` já exigem esses
 * campos no momento do INSERT/UPDATE — este checker é a rede de segurança
 * complementar no momento da APROVAÇÃO (EmployeeStatusService::approveRegistration()),
 * que hoje não revalida nada antes de ativar o colaborador.
 */
class EmployeeMteComplianceChecker
{
    /** @return array<string,string> campo => rótulo legível, só dos que estão faltando */
    public function missingFields(object|array $employee): array
    {
        $get = static fn (string $field) => is_array($employee) ? ($employee[$field] ?? null) : ($employee->{$field} ?? null);
        $missing = [];

        $required = [
            'name' => 'Nome completo',
            'cpf' => 'CPF',
            'pis_pasep' => 'PIS/NIS',
            'birth_date' => 'Data de nascimento',
            'sexo' => 'Sexo',
            'nacionalidade' => 'Nacionalidade',
            'estado_civil' => 'Estado civil',
            'grau_instrucao' => 'Escolaridade',
            'rg' => 'RG',
            'rg_orgao_emissor' => 'Órgão emissor do RG',
            'rg_data_expedicao' => 'Data de expedição do RG',
            'admission_date' => 'Data de admissão',
            'tipo_contrato' => 'Tipo de contrato',
            'salario_base' => 'Salário base',
        ];

        foreach ($required as $field => $label) {
            $value = $get($field);
            if ($value === null || $value === '') {
                $missing[$field] = $label;
            }
        }

        // Cargo, departamento e unidade: aceitam tanto o campo-catálogo (id) quanto o
        // texto legado — qualquer um dos dois presentes já conta como preenchido.
        if (empty($get('cargo')) && empty($get('position')) && empty($get('position_id'))) {
            $missing['cargo'] = 'Cargo';
        }
        if (empty($get('department')) && empty($get('department_id')) && empty($get('setor'))) {
            $missing['department'] = 'Departamento';
        }
        if (empty($get('work_unit')) && empty($get('work_unit_id'))) {
            $missing['work_unit'] = 'Unidade';
        }

        // CTPS: precisa declarar se tem carteira física; se declarou que sim, os
        // dados da carteira (número/série/UF/emissão) passam a ser obrigatórios.
        // CTPS Digital (sem carteira física) é aceita sem esses campos.
        $possuiCtpsFisica = $get('possui_ctps_fisica');
        if ($possuiCtpsFisica === null || $possuiCtpsFisica === '') {
            $missing['possui_ctps_fisica'] = 'Declaração de CTPS física/digital';
        } elseif ($this->truthy($possuiCtpsFisica)) {
            foreach ([
                'ctps_numero' => 'CTPS número',
                'ctps_serie' => 'CTPS série',
                'ctps_uf' => 'CTPS UF',
                'ctps_data_emissao' => 'CTPS data de emissão',
            ] as $field => $label) {
                $value = $get($field);
                if ($value === null || $value === '') {
                    $missing[$field] = $label;
                }
            }
        }

        return $missing;
    }

    public function isComplete(object|array $employee): bool
    {
        return $this->missingFields($employee) === [];
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 't'], true);
    }
}

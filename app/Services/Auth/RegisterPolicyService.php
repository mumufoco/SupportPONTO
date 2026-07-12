<?php

namespace App\Services\Auth;

use App\Models\SettingModel;

class RegisterPolicyService
{
    public function __construct(
        private readonly SettingModel $settingModel = new SettingModel(),
    ) {
    }

    public function selfRegistrationEnabled(): bool
    {
        return (bool) $this->settingModel->get('self_registration_enabled', false);
    }

    public function normalizePostData(array $postData): array
    {
        if (!empty($postData['cpf'])) {
            $postData['cpf'] = $this->cleanCPF((string) $postData['cpf']);
        }

        if (!empty($postData['email'])) {
            $postData['email'] = mb_strtolower(trim((string) $postData['email']));
        }

        if (!empty($postData['pis_pasep'])) {
            $postData['pis_pasep'] = preg_replace('/[^0-9]/', '', (string) $postData['pis_pasep']);
        }

        if (!empty($postData['cep'])) {
            $postData['cep'] = preg_replace('/[^0-9]/', '', (string) $postData['cep']);
        }

        foreach (['telefone', 'phone'] as $phoneField) {
            if (!empty($postData[$phoneField])) {
                $postData[$phoneField] = $this->normalizePhone((string) $postData[$phoneField]);
            }
        }

        if (!empty($postData['telefone']) && empty($postData['phone'])) {
            $postData['phone'] = $postData['telefone'];
        }

        if (!empty($postData['phone']) && empty($postData['telefone'])) {
            $postData['telefone'] = $postData['phone'];
        }

        return $postData;
    }

    public function selfRegistrationRules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[255]',
            'birth_date' => 'required|valid_date[Y-m-d]',
            'cpf' => 'required|exact_length[11]|regex_match[/^\d{11}$/]|is_unique[employees.cpf]',
            'rg' => 'required|min_length[5]|max_length[20]',
            'rg_orgao_emissor' => 'required|min_length[2]|max_length[10]',
            'rg_data_expedicao' => 'required|valid_date[Y-m-d]',
            'estado_civil' => 'required|in_list[solteiro,casado,divorciado,viuvo,uniao_estavel]',
            'nacionalidade' => 'required|min_length[3]|max_length[50]',
            'sexo' => 'required|in_list[masculino,feminino]',
            'cor_raca' => 'required|in_list[branca,preta,parda,amarela,indigena]',
            'grau_instrucao' => 'required|in_list[analfabeto,fundamental_incompleto,fundamental_completo,medio_incompleto,medio_completo,superior_incompleto,superior_completo,pos_graduacao]',
            'logradouro' => 'required|min_length[3]|max_length[255]',
            'numero' => 'required|min_length[1]|max_length[10]',
            'complemento' => 'permit_empty|max_length[100]',
            'bairro' => 'required|min_length[2]|max_length[100]',
            'municipio' => 'required|min_length[2]|max_length[100]',
            'uf' => 'required|exact_length[2]|in_list[AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO]',
            'cep' => 'required|exact_length[8]|regex_match[/^\d{8}$/]',
            'telefone' => 'required|valid_phone_br',
            'email' => 'required|valid_email|max_length[255]|is_unique[employees.email]',
            'ctps_numero' => 'required|min_length[5]|max_length[15]',
            'ctps_serie' => 'required|min_length[3]|max_length[10]',
            'ctps_uf' => 'required|exact_length[2]|in_list[AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO]',
            'ctps_data_emissao' => 'required|valid_date[Y-m-d]',
            'pis_pasep' => 'required|exact_length[11]|regex_match[/^\d{11}$/]|is_unique[employees.pis_pasep]',
            'admission_date' => 'required|valid_date[Y-m-d]',
            'cargo' => 'required|min_length[3]|max_length[100]',
            'salario_base' => 'required|decimal',
            'jornada_trabalho' => 'required|min_length[5]|max_length[50]',
            'tipo_contrato' => 'required|in_list[CLT,temporario,estagio,terceirizado]',
            'setor' => 'required|min_length[2]|max_length[100]',
            'horario_entrada' => 'required|regex_match[/^\d{2}:\d{2}$/]',
            'horario_saida' => 'required|regex_match[/^\d{2}:\d{2}$/]',
            'banco' => 'required|min_length[3]|max_length[50]',
            'agencia' => 'required|min_length[4]|max_length[10]',
            'conta' => 'required|min_length[5]|max_length[20]',
            'deficiencia' => 'permit_empty|in_list[sim,nao]|max_length[100]',
            'demission_date' => 'permit_empty|valid_date[Y-m-d]',
            'work_unit' => 'required',
            'department' => 'required',
            'position' => 'required',
            'password' => 'required|min_length[12]|strong_password',
            'password_confirm' => 'required|matches[password]',
            'lgpd_consent' => 'required|in_list[1]',
            'terms_accepted' => 'required|in_list[1]',
        ];
    }

    public function selfRegistrationMessages(): array
    {
        return [
            'email' => ['is_unique' => 'Este e-mail já está cadastrado.'],
            'cpf' => ['is_unique' => 'Este CPF já está cadastrado.'],
            'password' => ['strong_password' => 'A senha deve conter pelo menos 12 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.'],
            'password_confirm' => ['matches' => 'As senhas não coincidem.'],
            'lgpd_consent' => ['required' => 'Você deve concordar com o tratamento de dados pessoais.'],
            'terms_accepted' => ['required' => 'Você deve aceitar os termos de uso.'],
        ];
    }

    public function managerRules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[255]',
            'email' => 'required|valid_email|max_length[255]|is_unique[employees.email]',
            'cpf' => 'required|exact_length[11]|regex_match[/^\d{11}$/]|is_unique[employees.cpf]',
            'password' => 'required|min_length[12]|strong_password',
            'role' => 'required|valid_employee_role',
            'department' => 'required|min_length[2]',
            'position' => 'required|min_length[2]',
            'phone' => 'permit_empty|valid_phone_br',
        ];
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?: '';

        return match (strlen($digits)) {
            11 => preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $digits) ?? $digits,
            10 => preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $digits) ?? $digits,
            default => $phone,
        };
    }

    public function cleanCPF(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    public function validateCPF(string $cpf): bool
    {
        $cpf = $this->cleanCPF($cpf);
        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += ((int) $cpf[$c]) * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }
}

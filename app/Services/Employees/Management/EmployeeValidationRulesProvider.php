<?php

namespace App\Services\Employees\Management;

class EmployeeValidationRulesProvider
{
    private const UF_LIST = 'AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO';

    /** Regras da Aba "Documentação Geral" -- iguais em create e update. */
    private function documentationRules(): array
    {
        return [
            'titulo_eleitor_numero' => 'permit_empty|max_length[12]',
            'titulo_eleitor_zona' => 'permit_empty|max_length[5]',
            'titulo_eleitor_secao' => 'permit_empty|max_length[5]',
            'titulo_eleitor_uf' => 'permit_empty|exact_length[2]|in_list[' . self::UF_LIST . ']',
            'titulo_eleitor_municipio' => 'permit_empty|max_length[100]',
            'possui_cnh' => 'permit_empty|in_list[true,false,0,1,sim,nao]',
            'cnh_numero' => 'permit_empty|required_if_true[possui_cnh]|max_length[20]',
            'cnh_categoria' => 'permit_empty|required_if_true[possui_cnh]|in_list[A,B,C,D,E,AB,AC,AD,AE]',
            'cnh_data_emissao' => 'permit_empty|required_if_true[possui_cnh]|valid_date[Y-m-d]',
            'cnh_validade' => 'permit_empty|required_if_true[possui_cnh]|valid_date[Y-m-d]',
            'cnh_orgao_emissor' => 'permit_empty|required_if_true[possui_cnh]|max_length[20]',
            'cnh_uf' => 'permit_empty|required_if_true[possui_cnh]|exact_length[2]|in_list[' . self::UF_LIST . ']',
            'rg_uf' => 'permit_empty|exact_length[2]|in_list[' . self::UF_LIST . ']',
            'certificado_militar' => 'permit_empty|max_length[30]',

            // CTPS Digital (padrão desde 2019) substituiu a carteira física para a
            // maioria dos admitidos — não faz sentido exigir número/série de quem só
            // tem CTPS Digital. possui_ctps_fisica é obrigatório declarar (sim/não);
            // os campos da carteira física só ficam obrigatórios quando ela existe.
            //
            // IMPORTANTE: não prefixar com `permit_empty` — o núcleo do CodeIgniter só
            // continua avaliando required_with/required_without quando o valor está
            // vazio (ver Validation::fillPlaceholders()/getRuleset()); qualquer outra
            // regra encadeada depois de `permit_empty`, incluindo required_if_true
            // (regra própria da aplicação), é silenciosamente pulada quando o campo
            // está vazio. Testado e confirmado: `permit_empty|required_if_true[...]`
            // NUNCA bloqueia um campo vazio, mesmo com o gatilho true. Por isso aqui
            // fica só required_if_true, sem permit_empty nem checagem extra de
            // formato (min/max/exact_length) encadeada.
            'possui_ctps_fisica' => 'required|in_list[true,false,0,1]',
            'ctps_numero' => 'required_if_true[possui_ctps_fisica]',
            'ctps_serie' => 'required_if_true[possui_ctps_fisica]',
            'ctps_uf' => 'required_if_true[possui_ctps_fisica]',
            'ctps_data_emissao' => 'required_if_true[possui_ctps_fisica]',
        ];
    }

    public function createRules(): array
    {
        return $this->documentationRules() + [
            'name' => 'required|min_length[3]|max_length[255]',
            'birth_date' => 'required|valid_date[Y-m-d]',
            'cpf' => 'required|exact_length[11]|regex_match[/^\d{11}$/]|cpf_is_unique',
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
            'role' => 'required|valid_employee_role',
            'work_unit' => 'required',
            'password' => 'required|min_length[8]',
        ];
    }

    public function updateRules(int $id): array
    {
        return $this->documentationRules() + [
            'name' => 'required|min_length[3]|max_length[255]',
            'birth_date' => 'required|valid_date[Y-m-d]',
            'cpf' => "required|exact_length[11]|regex_match[/^\\d{11}$/]|cpf_is_unique[{$id}]",
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
            'email' => "required|valid_email|max_length[255]|is_unique[employees.email,id,{$id}]",
            'pis_pasep' => "required|exact_length[11]|regex_match[/^\\d{11}$/]|is_unique[employees.pis_pasep,id,{$id}]",
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
            'role' => 'required|valid_employee_role',
            'work_unit' => 'required',
        ];
    }
}

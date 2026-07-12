<?php

namespace App\Services\TXT;

use App\Models\SettingModel;

class TXTSettingsProvider
{
    /**
     * Perfil da empresa/REP usado pelos geradores de TXT — em especial pelo AFD
     * (Portaria MTE 671/2021), que exige diversos campos cadastrais do empregador,
     * do REP e do seu desenvolvedor no registro tipo "1" (cabeçalho).
     *
     * As novas chaves (companyDocType, companyCnoCaepf, developerDocType, developerCNPJ,
     * inpiRegistration) são opcionais nas configurações e têm defaults seguros — devem ser
     * preenchidas em "Configurações" para que o AFD saia com os dados reais do empregador
     * e do desenvolvedor do REP-P (SupportPONTO), em vez dos valores de fallback.
     *
     * @return array{
     *     companyName:string,
     *     companyCNPJ:string,
     *     companyAddress:string,
     *     companyDocType:string,
     *     companyCnoCaepf:string,
     *     developerDocType:string,
     *     developerCNPJ:string,
     *     inpiRegistration:string
     * }
     */
    public function getCompanyProfile(): array
    {
        try {
            $settingModel = new SettingModel();

            return [
                'companyName' => (string) $settingModel->get('company_name', 'Sistema de Ponto Eletrônico'),
                'companyCNPJ' => (string) $settingModel->get('company_cnpj', '00000000000000'),
                'companyAddress' => (string) $settingModel->get('company_address', ''),
                // Tipo de identificador do empregador no AFD: "1" = CNPJ, "2" = CPF.
                'companyDocType' => (string) $settingModel->get('company_doc_type', '1'),
                // CNO (Cadastro Nacional de Obras) ou CAEPF, quando existir — opcional.
                'companyCnoCaepf' => (string) $settingModel->get('company_cno_caepf', ''),
                // Identificação do fabricante/desenvolvedor do REP (SupportPONTO): "1" = CNPJ, "2" = CPF.
                'developerDocType' => (string) $settingModel->get('rep_developer_doc_type', '1'),
                'developerCNPJ' => (string) $settingModel->get('rep_developer_cnpj', ''),
                // Número de registro do REP-P no INPI (campo 7 do cabeçalho do AFD).
                // Fallback "99999999999999999" segue a convenção do próprio leiaute para
                // "processo inexistente" (usada no caso análogo do REP-A).
                'inpiRegistration' => (string) $settingModel->get('rep_inpi_registration', '99999999999999999'),
            ];
        } catch (\Throwable $e) {
            log_message('warning', 'Could not load settings in TXTService, using defaults: ' . $e->getMessage());

            return [
                'companyName' => 'Sistema de Ponto Eletrônico',
                'companyCNPJ' => '00000000000000',
                'companyAddress' => '',
                'companyDocType' => '1',
                'companyCnoCaepf' => '',
                'developerDocType' => '1',
                'developerCNPJ' => '',
                'inpiRegistration' => '99999999999999999',
            ];
        }
    }
}

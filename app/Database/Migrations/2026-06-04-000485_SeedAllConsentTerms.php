<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class SeedAllConsentTerms extends Migration
{
    public function up(): void
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $terms = [
            'biometric_fingerprint' => [
                'title'       => 'Termo de Consentimento — Biometria de Impressao Digital',
                'legal_basis' => 'Art. 11, II, "a" da LGPD (Lei 13.709/2018) — consentimento especifico e destacado do titular.',
                'body'        => "TERMO DE CONSENTIMENTO PARA TRATAMENTO DE DADOS BIOMETRICOS — IMPRESSAO DIGITAL\n\n1. IDENTIFICACAO DO CONTROLADOR\nSupport Solo Sondagens, doravante denominada CONTROLADORA.\n\n2. FINALIDADE DO TRATAMENTO\nOs dados biometricos de impressao digital serao tratados exclusivamente para:\na) Identificacao e autenticacao no sistema eletronico de ponto (REP-P), conforme Portaria MTE n. 671/2021;\nb) Controle de acesso as dependencias da empresa;\nc) Prevencao de fraudes e garantia da integridade dos registros de jornada.\n\n3. BASE LEGAL\nArt. 11, II, \"a\" da LGPD (Lei 13.709/2018) — consentimento especifico e destacado do titular.\nTambem aplicavel: Art. 7, V (execucao de contrato de trabalho) e Portaria MTE n. 671/2021.\n\n4. DADOS COLETADOS\n- Template biometrico numerico gerado por algoritmo de reconhecimento de impressao digital;\n- Metadados: data/hora, endereco IP, identificador do dispositivo.\n\n5. COMPARTILHAMENTO\nOs dados biometricos NAO serao compartilhados com terceiros, exceto por obrigacao legal.\n\n6. RETENCAO\nMantidos durante a vigencia do contrato e por 5 (cinco) anos apos o termino, conforme CLT e Portaria MTE n. 671/2021.\n\n7. DIREITOS DO TITULAR\nAcesso, correcao, eliminacao e revogacao do consentimento a qualquer momento.\n\n8. CONSENTIMENTO\nAo aceitar este termo, o colaborador declara ter lido e compreendido todas as clausulas, consentindo de forma livre, informada e inequivoca com o tratamento dos seus dados biometricos de impressao digital para as finalidades descritas.",
            ],
            'geolocation' => [
                'title'       => 'Termo de Consentimento para Uso de Geolocalizacao',
                'legal_basis' => 'Art. 7, I da LGPD (Lei 13.709/2018) — consentimento especifico e destacado do titular.',
                'body'        => "TERMO DE CONSENTIMENTO PARA TRATAMENTO DE DADOS DE GEOLOCALIZACAO\n\n1. IDENTIFICACAO DO CONTROLADOR\nSupport Solo Sondagens, doravante denominada CONTROLADORA.\n\n2. FINALIDADE DO TRATAMENTO\nOs dados de geolocalizacao serao tratados exclusivamente para:\na) Validacao do local de registro de ponto eletronico (REP-P);\nb) Verificacao de presenca em obra ou local de trabalho autorizado;\nc) Cumprimento da Portaria MTE n. 671/2021 para trabalhadores externos.\n\n3. BASE LEGAL\nArt. 7, I da LGPD (Lei 13.709/2018) — consentimento especifico e destacado do titular.\n\n4. DADOS COLETADOS\n- Coordenadas GPS (latitude e longitude) no momento do registro de ponto;\n- Precisao da localizacao, data e hora.\n\n5. COMPARTILHAMENTO\nOs dados de localizacao NAO serao compartilhados com terceiros sem consentimento adicional.\n\n6. RETENCAO\nMantidos durante a vigencia do contrato e por 5 (cinco) anos apos o termino.\n\n7. DIREITOS DO TITULAR\nO colaborador pode revogar este consentimento a qualquer momento.\n\n8. CONSENTIMENTO\nAo aceitar este termo, o colaborador autoriza o uso da sua localizacao GPS exclusivamente para validacao do registro de ponto.",
            ],
            'data_processing' => [
                'title'       => 'Termo de Consentimento para Tratamento de Dados Pessoais',
                'legal_basis' => 'Art. 7, V da LGPD (Lei 13.709/2018) — execucao de contrato de trabalho.',
                'body'        => "TERMO DE CONSENTIMENTO PARA TRATAMENTO DE DADOS PESSOAIS\n\n1. IDENTIFICACAO DO CONTROLADOR\nSupport Solo Sondagens, doravante denominada CONTROLADORA.\n\n2. FINALIDADE DO TRATAMENTO\nOs dados pessoais serao tratados para:\na) Gestao de recursos humanos e relacao de emprego;\nb) Processamento de folha de pagamento, ferias e FGTS;\nc) Cumprimento de obrigacoes trabalhistas e previdenciarias;\nd) Controle de jornada conforme Portaria MTE n. 671/2021;\ne) Comunicacao de informacoes relevantes ao colaborador.\n\n3. BASE LEGAL\nArt. 7, V da LGPD — execucao de contrato de trabalho.\nTambem aplicavel: Art. 7, II — cumprimento de obrigacao legal.\n\n4. DADOS COLETADOS\nNome completo, CPF, RG, data de nascimento, endereco, e-mail, telefone, PIS/PASEP, dados bancarios, historico de ponto e jornada.\n\n5. COMPARTILHAMENTO\nDados poderao ser compartilhados com: orgaos governamentais (eSocial, CAGED), banco para pagamento de salario e auditores externos quando exigido por lei.\n\n6. RETENCAO\nDados mantidos durante a vigencia do contrato e pelo prazo legal minimo de 5 (cinco) anos apos o termino.\n\n7. CONSENTIMENTO\nAo aceitar este termo, o colaborador confirma ciencia e concordancia com o tratamento dos seus dados pessoais para as finalidades descritas.",
            ],
            'marketing' => [
                'title'       => 'Termo de Consentimento para Comunicacoes de Marketing',
                'legal_basis' => 'Art. 7, I da LGPD (Lei 13.709/2018) — consentimento especifico e destacado do titular.',
                'body'        => "TERMO DE CONSENTIMENTO PARA COMUNICACOES DE MARKETING\n\n1. IDENTIFICACAO DO CONTROLADOR\nSupport Solo Sondagens, doravante denominada CONTROLADORA.\n\n2. FINALIDADE DO TRATAMENTO\nOs dados de contato serao utilizados para envio de:\na) Comunicados sobre eventos e treinamentos da empresa;\nb) Informacoes sobre beneficios, programas de bem-estar e oportunidades internas;\nc) Noticias e atualizacoes relevantes sobre a empresa.\n\n3. BASE LEGAL\nArt. 7, I da LGPD — consentimento especifico e destacado do titular.\n\n4. REVOGACAO\nO consentimento pode ser revogado a qualquer momento, sem qualquer prejuizo.\n\n5. CONSENTIMENTO\nAo aceitar este termo, o colaborador autoriza o recebimento de comunicacoes e informativos da empresa.",
            ],
            'data_sharing' => [
                'title'       => 'Termo de Consentimento para Compartilhamento de Dados com Parceiros',
                'legal_basis' => 'Art. 7, V da LGPD (Lei 13.709/2018) — execucao de contrato de trabalho e consentimento.',
                'body'        => "TERMO DE CONSENTIMENTO PARA COMPARTILHAMENTO DE DADOS COM PARCEIROS\n\n1. IDENTIFICACAO DO CONTROLADOR\nSupport Solo Sondagens, doravante denominada CONTROLADORA.\n\n2. FINALIDADE DO TRATAMENTO\nOs dados poderao ser compartilhados com parceiros para:\na) Administracao de plano de saude e odontologico;\nb) Vale-refeicao e vale-alimentacao;\nc) Vale-transporte e outros beneficios trabalhistas;\nd) Seguro de vida em grupo.\n\n3. BASE LEGAL\nArt. 7, V da LGPD — execucao de contrato de trabalho.\nArt. 7, I — consentimento para compartilhamentos adicionais.\n\n4. DADOS COMPARTILHADOS\nNome, CPF, data de nascimento, endereco, dados de dependentes (quando aplicavel).\n\n5. REVOGACAO\nO consentimento pode ser revogado, podendo resultar na interrupcao de beneficios vinculados.\n\n6. CONSENTIMENTO\nAo aceitar este termo, o colaborador autoriza o compartilhamento dos dados listados com os parceiros de beneficios da empresa.",
            ],
        ];

        foreach ($terms as $type => $data) {
            $exists = $db->table('consent_terms')
                ->where('type', $type)
                ->where('active', true)
                ->countAllResults();

            if ($exists) {
                continue;
            }

            $db->table('consent_terms')->insert([
                'type'        => $type,
                'version'     => '1.0',
                'title'       => $data['title'],
                'body'        => $data['body'],
                'legal_basis' => $data['legal_basis'],
                'active'      => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        \Config\Database::connect()->table('consent_terms')
            ->whereIn('type', ['biometric_fingerprint', 'geolocation', 'data_processing', 'marketing', 'data_sharing'])
            ->delete();
    }
}

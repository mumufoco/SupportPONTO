<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConsentTermsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'type'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'version'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'body'          => ['type' => 'TEXT'],
            'legal_basis'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'active'        => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'    => ['type' => 'INT', 'null' => true],
            'created_at'    => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('consent_terms', true);

        // Add unique constraint separately (PostgreSQL compatible)
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS consent_terms_type_version_idx ON consent_terms (type, version)');

        $this->db->table('consent_terms')->insert([
            'type'        => 'biometric_face',
            'version'     => '1.0',
            'title'       => 'Termo de Consentimento para Uso de Dados Biometricos Faciais',
            'body'        => $this->defaultBiometricTerm(),
            'legal_basis' => 'Art. 11, II, "a" da LGPD (Lei 13.709/2018) - consentimento especifico e destacado do titular.',
            'active'      => true,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('consent_terms', true);
    }

    private function defaultBiometricTerm(): string
    {
        return 'TERMO DE CONSENTIMENTO PARA TRATAMENTO DE DADOS BIOMETRICOS FACIAIS

1. IDENTIFICACAO DO CONTROLADOR
Support Solo Sondagens, doravante denominada CONTROLADORA.

2. FINALIDADE DO TRATAMENTO
Os dados biometricos faciais serao tratados exclusivamente para:
a) Identificacao e autenticacao no sistema eletronico de ponto (REP-P), conforme Portaria MTE n. 671/2021;
b) Controle de acesso as dependencias da empresa;
c) Prevencao de fraudes e integridade dos registros de jornada.

3. BASE LEGAL
Art. 11, II, "a" da LGPD (Lei 13.709/2018) - consentimento especifico e destacado. Tambem aplicavel: Art. 7, V (execucao de contrato de trabalho) e Portaria MTE n. 671/2021.

4. DADOS COLETADOS
- Fotografia do rosto (capturada no ato do cadastro);
- Template biometrico numerico gerado por algoritmo de reconhecimento facial;
- Metadados: data/hora, endereco IP, identificador do dispositivo.

5. COMPARTILHAMENTO
Os dados biometricos NAO serao compartilhados com terceiros, exceto por obrigacao legal ou ordem judicial.

6. RETENCAO E ELIMINACAO
Mantidos durante a vigencia do contrato e por 5 (cinco) anos apos o termino, conforme CLT e Portaria MTE n. 671/2021.

7. DIREITOS DO TITULAR (Arts. 17 a 22, LGPD)
Confirmacao, acesso, correcao, eliminacao e revogacao do consentimento a qualquer momento via portal ou DPO.

8. CONSENTIMENTO
Ao aceitar este termo, o colaborador declara ter lido e compreendido todas as clausulas, consentindo de forma livre, informada e inequivoca com o tratamento dos seus dados biometricos faciais para as finalidades descritas.';
    }
}

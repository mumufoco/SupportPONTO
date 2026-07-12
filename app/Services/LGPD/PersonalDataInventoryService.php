<?php

namespace App\Services\LGPD;

/**
 * Inventário de dados pessoais/sensíveis tratado pelo SupportPONTO.
 *
 * Mantém a matriz de tratamento em código para que exportações, relatórios
 * e auditorias usem a mesma fonte de verdade. Não armazena segredos nem dados
 * reais; apenas metadados de governança LGPD.
 */
class PersonalDataInventoryService
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            'identificacao' => [
                'label' => 'Identificação civil e funcional',
                'category' => 'dados_pessoais',
                'examples' => ['nome', 'e-mail', 'CPF', 'RG', 'PIS/PASEP', 'matrícula/código único'],
                'tables' => ['employees'],
                'legal_basis' => 'LGPD Art. 7º, V e II — contrato de trabalho e obrigação legal',
                'purpose' => 'Identificar o colaborador e manter registros trabalhistas obrigatórios.',
                'retention_key' => 'employee_master_data',
                'sensitivity' => 'alta',
                'access_profiles' => ['admin', 'rh', 'dpo'],
            ],
            'trabalhista' => [
                'label' => 'Dados trabalhistas e jornada',
                'category' => 'dados_pessoais',
                'examples' => ['cargo', 'setor', 'salário base', 'jornada', 'batidas de ponto', 'advertências'],
                'tables' => ['employees', 'time_punches', 'warnings', 'schedules', 'timesheet_consolidated'],
                'legal_basis' => 'LGPD Art. 7º, II e V — obrigação legal e execução de contrato',
                'purpose' => 'Cumprir obrigações trabalhistas, controle de jornada e gestão operacional.',
                'retention_key' => 'labor_records',
                'sensitivity' => 'alta',
                'access_profiles' => ['admin', 'rh', 'gestor', 'auditor', 'dpo'],
            ],
            'biometria' => [
                'label' => 'Biometria facial e digital',
                'category' => 'dados_sensiveis',
                'examples' => ['templates biométricos', 'hash de imagem facial', 'arquivo facial criptografado', 'qualidade da captura'],
                'tables' => ['biometric_templates', 'facial_recognition_logs', 'biometric_encryption_audit'],
                'legal_basis' => 'LGPD Art. 11, II, a — obrigação legal/regulatória aplicável ao controle de ponto',
                'purpose' => 'Autenticar o registro de ponto e prevenir fraude operacional.',
                'retention_key' => 'biometric_templates',
                'sensitivity' => 'critica',
                'access_profiles' => ['admin', 'rh', 'dpo'],
                'controls' => ['criptografia', 'expurgo por revogação/retencao', 'auditoria obrigatoria', 'não exportar template bruto'],
            ],
            'geolocalizacao' => [
                'label' => 'Geolocalização',
                'category' => 'dados_pessoais',
                'examples' => ['latitude', 'longitude', 'cerca geográfica', 'origem da batida'],
                'tables' => ['time_punches', 'geofences'],
                'legal_basis' => 'LGPD Art. 7º, I/V — consentimento quando aplicável ou execução contratual operacional',
                'purpose' => 'Validar batidas externas e controle operacional em campo.',
                'retention_key' => 'geolocation_records',
                'sensitivity' => 'alta',
                'access_profiles' => ['admin', 'rh', 'gestor', 'dpo'],
            ],
            'seguranca' => [
                'label' => 'Segurança, auditoria e autenticação',
                'category' => 'dados_pessoais',
                'examples' => ['IP', 'user-agent', 'logs de auditoria', 'tokens OAuth', '2FA'],
                'tables' => ['audit_logs', 'oauth_tokens', 'user_consents'],
                'legal_basis' => 'LGPD Art. 7º, II e IX — obrigação legal e legítimo interesse em segurança',
                'purpose' => 'Prevenir fraude, investigar incidentes e manter rastreabilidade.',
                'retention_key' => 'security_audit_logs',
                'sensitivity' => 'alta',
                'access_profiles' => ['admin', 'auditor', 'dpo'],
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function personalData(): array
    {
        return array_values(array_filter($this->all(), static fn(array $item): bool => $item['category'] === 'dados_pessoais'));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function sensitiveData(): array
    {
        return array_values(array_filter($this->all(), static fn(array $item): bool => $item['category'] === 'dados_sensiveis'));
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = $this->all();

        return [
            'total_categories' => count($all),
            'personal_categories' => count($this->personalData()),
            'sensitive_categories' => count($this->sensitiveData()),
            'generated_at' => date('c'),
            'items' => $all,
        ];
    }
}

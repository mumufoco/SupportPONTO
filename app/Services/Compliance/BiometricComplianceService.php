<?php

namespace App\Services\Compliance;

use App\Models\EmployeeModel;
use App\Models\UserConsentModel;

/**
 * Serviço de conformidade biométrica com dados reais do banco de dados.
 */
class BiometricComplianceService
{
    private EmployeeModel    $employeeModel;
    private UserConsentModel $consentModel;

    public function __construct(
        ?EmployeeModel    $employeeModel = null,
        ?UserConsentModel $consentModel  = null
    ) {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->consentModel  = $consentModel  ?? new UserConsentModel();
    }

    /**
     * Resumo do status geral de consentimento biométrico.
     * Avalia se há pendências críticas na base de funcionários.
     */
    public function getConsentSummary(): array
    {
        try {
            $totalAtivos    = $this->employeeModel->where('active', true)->countAllResults();
            $comBiometria   = $this->employeeModel
                ->where('active', true)
                ->groupStart()
                    ->where('has_face_biometric', true)
                    ->orWhere('has_fingerprint_biometric', true)
                ->groupEnd()
                ->countAllResults();

            $comConsentimento = $this->consentModel
                ->where('granted', true)
                ->whereIn('consent_type', ['biometric_face', 'biometric_fingerprint', 'biometric_data'])
                ->where('revoked_at', null)
                ->countAllResults();

            $semConsentimento = $comBiometria - $comConsentimento;

            if ($semConsentimento > 0) {
                return [
                    'status'      => 'Atenção',
                    'label'       => 'Consentimento biométrico com pendências',
                    'description' => "{$semConsentimento} funcionário(s) com biometria cadastrada sem consentimento ativo registrado. Regularização necessária antes da próxima auditoria.",
                ];
            }

            if ($comBiometria === 0) {
                return [
                    'status'      => 'Pendente',
                    'label'       => 'Nenhuma biometria cadastrada',
                    'description' => 'Nenhum funcionário ativo possui biometria cadastrada. Configure e registre consentimentos antes de ativar a biometria.',
                ];
            }

            return [
                'status'      => 'Regular',
                'label'       => 'Consentimento biométrico em conformidade',
                'description' => "{$comConsentimento} consentimento(s) ativo(s) registrado(s). Todos os funcionários com biometria possuem base legal configurada.",
            ];
        } catch (\Throwable $e) {
            log_structured('warning', 'compliance.biometric_consent_summary_failed',
                ['error' => $e->getMessage()]);
            return [
                'status'      => 'Indisponível',
                'label'       => 'Dados temporariamente indisponíveis',
                'description' => 'Não foi possível carregar o resumo de consentimentos. Verifique a conectividade com o banco de dados.',
            ];
        }
    }

    /**
     * Cartões com métricas reais de biometria e consentimento.
     */
    public function getBiometricProfileCards(): array
    {
        try {
            $totalAtivos    = $this->employeeModel->where('active', true)->countAllResults();
            $comFacial      = $this->employeeModel->where('active', true)->where('has_face_biometric', true)->countAllResults();
            $comDigital     = $this->employeeModel->where('active', true)->where('has_fingerprint_biometric', true)->countAllResults();
            $comConsentimento = $this->consentModel
                ->where('granted', true)
                ->whereIn('consent_type', ['biometric_face', 'biometric_fingerprint', 'biometric_data'])
                ->where('revoked_at', null)
                ->countAllResults();

            $percentualAdesao = $totalAtivos > 0
                ? round(($comFacial + $comDigital > 0 ? max($comFacial, $comDigital) : 0) / $totalAtivos * 100)
                : 0;

            return [
                ['value' => "{$comConsentimento}", 'label' => 'Consentimentos ativos'],
                ['value' => "{$comFacial}",        'label' => 'Cadastros faciais'],
                ['value' => "{$comDigital}",       'label' => 'Impressões digitais'],
                ['value' => "{$percentualAdesao}%",'label' => 'Adesão biométrica'],
            ];
        } catch (\Throwable $e) {
            log_structured('warning', 'compliance.biometric_cards_failed',
                ['error' => $e->getMessage()]);
            return [
                ['value' => '—', 'label' => 'Consentimentos ativos'],
                ['value' => '—', 'label' => 'Cadastros faciais'],
                ['value' => '—', 'label' => 'Impressões digitais'],
                ['value' => '—', 'label' => 'Adesão biométrica'],
            ];
        }
    }

    /**
     * Diretrizes estruturais — conteúdo normativo, adequado como texto fixo
     * pois reflete a legislação (LGPD Art. 11 e Portaria MTE 671/2021).
     */
    public function getBiometricGuidelines(): array
    {
        return [
            [
                'title' => 'Consentimento explícito (LGPD Art. 11)',
                'desc'  => 'Registrar ciência e autorização antes do uso de biometria facial ou digital. O consentimento deve ser específico, informado e inequívoco.',
            ],
            [
                'title' => 'Rastreabilidade (Portaria MTE 671/2021)',
                'desc'  => 'Toda alteração biométrica deve possuir histórico mínimo de data, responsável e contexto. Exigência para validade legal do registro de ponto.',
            ],
            [
                'title' => 'Revogação controlada (LGPD Art. 15)',
                'desc'  => 'Mudanças de status e revogações precisam de trilha administrativa clara. Dados biométricos devem ser removidos no prazo configurado após revogação.',
            ],
        ];
    }
}


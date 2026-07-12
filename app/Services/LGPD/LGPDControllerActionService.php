<?php

namespace App\Services\LGPD;

class LGPDControllerActionService
{
    public function consentTypes(): array
    {
        return [
            'biometric_face' => [
                'label' => 'Biometria Facial',
                'purpose' => 'Captura e processamento de dados biométricos faciais para registro de ponto eletrônico',
                'legal_basis' => 'LGPD Art. 11, II, a - obrigação legal/regulatória aplicável ao controle de jornada',
                'required' => true,
            ],
            'biometric_fingerprint' => [
                'label' => 'Biometria Digital (Impressão Digital)',
                'purpose' => 'Captura e processamento de impressões digitais para registro de ponto eletrônico',
                'legal_basis' => 'LGPD Art. 11, II, a - obrigação legal/regulatória aplicável ao controle de jornada',
                'required' => true,
            ],
            'geolocation' => [
                'label' => 'Geolocalização',
                'purpose' => 'Coleta de dados de localização GPS para validação de registros de ponto em campo',
                'legal_basis' => 'LGPD Art. 7º, I - consentimento específico e destacado',
                'required' => false,
            ],
            'data_processing' => [
                'label' => 'Processamento de Dados Pessoais',
                'purpose' => 'Processamento de dados pessoais para gestão de recursos humanos e folha de pagamento',
                'legal_basis' => 'LGPD Art. 7º, V - execução de contrato de trabalho',
                'required' => true,
            ],
            'marketing' => [
                'label' => 'Comunicações de Marketing',
                'purpose' => 'Envio de comunicações sobre eventos, treinamentos e benefícios da empresa',
                'legal_basis' => 'LGPD Art. 7º, I - consentimento específico e destacado',
                'required' => false,
            ],
            'data_sharing' => [
                'label' => 'Compartilhamento de Dados',
                'purpose' => 'Compartilhamento de dados com parceiros para administração de benefícios (plano de saúde, vale-refeição, etc)',
                'legal_basis' => 'LGPD Art. 7º, V - execução de contrato de trabalho',
                'required' => false,
            ],
        ];
    }

    public function listExportsForEmployee(int $employeeId): array
    {
        return \Config\Database::connect()->table('data_exports')
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function recentExportForEmployee(int $employeeId): ?object
    {
        $row = \Config\Database::connect()->table('data_exports')
            ->where('employee_id', $employeeId)
            ->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getRow();

        return $row ?: null;
    }

    public function completedExportForDownload(string $exportId, int $employeeId): ?object
    {
        $row = \Config\Database::connect()->table('data_exports')
            ->where('export_id', $exportId)
            ->where('employee_id', $employeeId)
            ->where('status', 'completed')
            ->get()
            ->getRow();

        return $row ?: null;
    }

    public function registerDownload(object $export): void
    {
        \Config\Database::connect()->table('data_exports')
            ->where('id', $export->id)
            ->update([
                'download_count' => ($export->download_count ?? 0) + 1,
                'last_downloaded_at' => date('Y-m-d H:i:s'),
            ]);
    }
}

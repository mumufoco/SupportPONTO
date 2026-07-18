<?php

namespace App\Services\Warning;

class WarningPdfContentService
{
    public function generateWarningHtml(object $warning, object $employee, object $issuer, string $companyLogo, string $companyName, string $companyCnpj): string
    {
        $warningType = $this->warningTypeLabel($warning->warning_type);

        $html = '
        <style>
            h1 { text-align: center; font-size: 18pt; margin-bottom: 5mm; }
            h2 { font-size: 14pt; margin-top: 8mm; margin-bottom: 3mm; border-bottom: 1px solid #333; }
            .header { text-align: center; margin-bottom: 10mm; }
            .company-info { font-size: 9pt; color: #666; }
            .box { border: 1px solid #333; padding: 5mm; margin: 5mm 0; background-color: #f9f9f9; }
            .label { font-weight: bold; }
            .signature-box { border: 1px solid #999; height: 20mm; margin-top: 3mm; }
            .footer { font-size: 8pt; color: #666; text-align: center; margin-top: 10mm; }
        </style>

        <div class="header">';

        if (file_exists($companyLogo)) {
            $html .= '<img src="' . $companyLogo . '" width="60mm" /><br>';
        }

        $html .= '
            <div class="company-info">
                ' . $companyName . '<br>
                CNPJ: ' . $companyCnpj . '
            </div>
        </div>

        <h1>ADVERTÊNCIA ' . $warningType . '</h1>

        <h2>DADOS DO FUNCIONÁRIO</h2>
        <div class="box">
            <strong>Nome:</strong> ' . htmlspecialchars($employee->name) . '<br>
            <strong>CPF:</strong> ' . ($employee->cpf ?? 'Não informado') . '<br>
            <strong>Matrícula:</strong> ' . ($employee->id) . '<br>
            <strong>Departamento:</strong> ' . ($employee->department ?? 'Não informado') . '<br>
            <strong>Cargo:</strong> ' . ($employee->position ?? 'Não informado') . '
        </div>

        <h2>DATA DA OCORRÊNCIA</h2>
        <p><strong>' . date('d/m/Y', strtotime($warning->occurrence_date)) . '</strong></p>

        <h2>DESCRIÇÃO DOS FATOS</h2>
        <div class="box">
            ' . nl2br(htmlspecialchars($warning->reason)) . '
        </div>

        <h2>CLÁUSULAS LEGAIS</h2>
        <p style="font-size: 9pt; text-align: justify;">
            De acordo com o artigo 482 da Consolidação das Leis do Trabalho (CLT),
            constituem justa causa para rescisão do contrato de trabalho pelo empregador:
            atos de indisciplina ou insubordinação, desídia no desempenho das respectivas funções,
            e demais infrações previstas na legislação trabalhista vigente.
        </p>
        <p style="font-size: 9pt; text-align: justify;">
            Esta advertência é emitida em conformidade com o Regulamento Interno da empresa
            e serve como registro formal da ocorrência descrita.
        </p>';

        if (!empty($warning->evidence_files)) {
            $html .= '
            <h2>EVIDÊNCIAS ANEXAS</h2>
            <ul style="font-size: 9pt;">';

            foreach ($warning->evidence_files as $index => $file) {
                $html .= '<li>Anexo ' . ($index + 1) . ': ' . htmlspecialchars(basename($file)) . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '
        <h2>ASSINATURAS</h2>

        <p><strong>Gestor/Administrador:</strong></p>
        <p>' . htmlspecialchars($issuer->name) . '<br>
        Data: ' . date('d/m/Y H:i') . '</p>
        <div class="signature-box">
            <p style="text-align: center; padding-top: 7mm; color: #999;">
                [Assinatura Digital ICP-Brasil]
            </p>
        </div>

        <p style="margin-top: 10mm;"><strong>Funcionário:</strong></p>
        <p>' . htmlspecialchars($employee->name) . '</p>
        <div class="signature-box">
            <p style="text-align: center; padding-top: 7mm; color: #999;">
                [Aguardando assinatura]
            </p>
        </div>

        <div class="footer">
            Documento gerado eletronicamente em ' . date('d/m/Y H:i:s') . '<br>
            Este documento possui validade legal e está protegido por assinatura digital.
        </div>
        ';

        return $html;
    }

    public function generateFinalHtml(object $warning, object $employee, object $issuer, string $companyLogo, string $companyName, string $companyCnpj): string
    {
        $warningType = $this->warningTypeLabel($warning->warning_type);
        $statusCss = $warning->status === 'assinado' ? '#28a745; color: white;' : '#dc3545; color: white;';

        $html = '
        <style>
            h1 { text-align: center; font-size: 18pt; margin-bottom: 5mm; }
            h2 { font-size: 14pt; margin-top: 8mm; margin-bottom: 3mm; border-bottom: 1px solid #333; }
            .header { text-align: center; margin-bottom: 10mm; }
            .company-info { font-size: 9pt; color: #666; }
            .box { border: 1px solid #333; padding: 5mm; margin: 5mm 0; background-color: #f9f9f9; }
            .signature-box { border: 1px solid #333; padding: 5mm; margin-top: 3mm; background-color: #ffffcc; }
            .footer { font-size: 8pt; color: #666; text-align: center; margin-top: 10mm; }
            .status-badge { background-color: ' . $statusCss . ' padding: 2mm 4mm; border-radius: 3mm; font-size: 9pt; }
        </style>

        <div class="header">';

        if (file_exists($companyLogo)) {
            $html .= '<img src="' . $companyLogo . '" width="60mm" /><br>';
        }

        $html .= '
            <div class="company-info">
                ' . $companyName . '<br>
                CNPJ: ' . $companyCnpj . '
            </div>
        </div>

        <h1>ADVERTÊNCIA ' . $warningType . '</h1>

        <p style="text-align: center;">
            <span class="status-badge">' . strtoupper($warning->status) . '</span>
        </p>

        <h2>DADOS DO FUNCIONÁRIO</h2>
        <div class="box">
            <strong>Nome:</strong> ' . htmlspecialchars($employee->name) . '<br>
            <strong>CPF:</strong> ' . ($employee->cpf ?? 'Não informado') . '<br>
            <strong>Matrícula:</strong> ' . ($employee->id) . '<br>
            <strong>Departamento:</strong> ' . ($employee->department ?? 'Não informado') . '<br>
            <strong>Cargo:</strong> ' . ($employee->position ?? 'Não informado') . '
        </div>

        <h2>DATA DA OCORRÊNCIA</h2>
        <p><strong>' . date('d/m/Y', strtotime($warning->occurrence_date)) . '</strong></p>

        <h2>DESCRIÇÃO DOS FATOS</h2>
        <div class="box">' . nl2br(htmlspecialchars($warning->reason)) . '</div>';

        if (!empty($warning->evidence_files)) {
            $html .= '<h2>EVIDÊNCIAS ANEXAS</h2><ul style="font-size: 9pt;">';
            foreach ($warning->evidence_files as $index => $file) {
                $html .= '<li>Anexo ' . ($index + 1) . ': ' . htmlspecialchars(basename($file)) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h2>ASSINATURAS</h2>
        <p><strong>Gestor/Administrador:</strong></p>
        <div class="signature-box">
            <p><strong>' . htmlspecialchars($issuer->name) . '</strong><br>
            Data: ' . date('d/m/Y', strtotime($warning->created_at)) . '<br>
            <em style="font-size: 8pt;">Assinado digitalmente via Certificado ICP-Brasil</em></p>
        </div>

        <p style="margin-top: 10mm;"><strong>Funcionário:</strong></p>';

        if ($warning->status === 'assinado' && $warning->employee_signature) {
            $html .= '<div class="signature-box"><p><strong>' . htmlspecialchars($employee->name) . '</strong><br>
                Data: ' . date('d/m/Y H:i', strtotime($warning->employee_signed_at)) . '<br>
                <em style="font-size: 8pt;">' . htmlspecialchars($warning->employee_signature) . '</em></p></div>';
        } elseif ($warning->status === 'recusado' && !empty($warning->witnesses ?? [])) {
            $html .= '<div class="signature-box" style="background-color: #ffcccc;"><p><strong>RECUSADO PELO FUNCIONÁRIO</strong></p>';
            foreach ($warning->witnesses as $i => $witness) {
                $html .= '<p style="margin-top: 5mm;"><strong>Testemunha ' . ($i + 1) . ':</strong><br>
                Nome: ' . htmlspecialchars($witness->witness_name) . '<br>
                CPF: ' . htmlspecialchars($witness->witness_cpf) . '<br>
                <em style="font-size: 8pt;">Testemunha presencial da recusa de assinatura</em></p>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="footer">Documento gerado eletronicamente em ' . date('d/m/Y H:i:s') . '<br>
            Este documento possui validade legal e está protegido por assinatura digital.</div>';

        return $html;
    }

    private function warningTypeLabel(string $warningType): string
    {
        return [
            'verbal' => 'VERBAL',
            'escrita' => 'ESCRITA',
            'suspensao' => 'SUSPENSÃO',
        ][$warningType] ?? strtoupper($warningType);
    }
}

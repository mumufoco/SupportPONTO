<?php

namespace App\Services\Pdf;

class PdfDocumentFactory
{
    public function __construct(
        private readonly string  $companyName,
        private readonly string  $companyCnpj   = '',
        private readonly ?string $logoPath       = null,
    ) {}

    public function create(string $title, string $orientation = 'P'): GotenbergPdfDocument
    {
        $pdf = new GotenbergPdfDocument($orientation, PDF_UNIT, 'A4', true, 'UTF-8', false);

        $pdf->SetCreator($this->companyName);
        $pdf->SetAuthor($this->companyName);
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);

        // ── Cabeçalho personalizado ──────────────────────────────────────────
        $logoWidth = 0;
        if ($this->logoPath && file_exists($this->logoPath)) {
            $logoWidth = 22;
        }
        $subtitle = $this->companyCnpj ? 'CNPJ: ' . $this->companyCnpj : '';
        $pdf->SetHeaderData($this->logoPath ?? '', $logoWidth, $this->companyName, $subtitle);
        $pdf->setHeaderFont(['helvetica', 'B', 10]);

        // ── Rodapé ───────────────────────────────────────────────────────────
        $pdf->setFooterFont(['helvetica', '', 7]);
        $pdf->setFooterData([100, 100, 100], [200, 200, 200]);

        // ── Margens e layout ─────────────────────────────────────────────────
        $pdf->SetMargins(12, 28, 12);
        $pdf->SetHeaderMargin(6);
        $pdf->SetFooterMargin(8);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->AddPage();

        return $pdf;
    }

    /**
     * Bloco HTML de cabeçalho de relatório para usar no início do conteúdo.
     */
    public function reportHeader(string $title, string $period, string $generatedBy = ''): string
    {
        $cnpjHtml = $this->companyCnpj ? '<br><span style="font-size:7pt;color:#555;">CNPJ: ' . esc($this->companyCnpj) . '</span>' : '';
        $byHtml   = $generatedBy ? '<span style="font-size:7pt;color:#555;">Emitido por: ' . esc($generatedBy) . '</span><br>' : '';

        return '
<table border="0" cellpadding="4" cellspacing="0" style="width:100%;border-bottom:2px solid #1B3A6B;margin-bottom:6px;">
  <tr>
    <td width="70%" style="vertical-align:bottom;">
      <span style="font-size:13pt;font-weight:bold;color:#1B3A6B;">' . esc($title) . '</span>
      <br><span style="font-size:8pt;color:#444;">' . esc($period) . '</span>
    </td>
    <td width="30%" align="right" style="vertical-align:bottom;font-size:7.5pt;color:#555;">
      <strong style="font-size:8.5pt;color:#1B3A6B;">' . esc($this->companyName) . '</strong>'
      . $cnpjHtml . '<br>'
      . $byHtml
      . 'Gerado em: ' . date('d/m/Y H:i')
    . '</td>
  </tr>
</table>';
    }
}

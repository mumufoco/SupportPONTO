<?php

namespace App\Services\Pdf;

/**
 * Fachada compatível com o subconjunto da API do TCPDF usado pelos
 * exportadores de PDF do sistema (writeHTML/Output + chamadas de setup que
 * viram no-op), mas que renderiza via Gotenberg (Chromium headless) em vez
 * do TCPDF. Ver GotenbergClient para o motivo da migração.
 *
 * Os content builders (PdfAdministrativeReportContentBuilder,
 * PdfOperationalReportContentBuilder, WarningPdfContentService, etc.) já
 * produzem HTML puro — só chamam $pdf->writeHTML($html) e $pdf->Output(...)
 * — então esta classe é um drop-in replacement sem precisar reescrever
 * esses builders.
 */
class GotenbergPdfDocument
{
    private string $htmlBuffer = '';
    private string $title = '';
    private bool $landscape;
    private float $marginTopMm = 15;
    private float $marginBottomMm = 15;
    private float $marginLeftMm = 15;
    private float $marginRightMm = 15;
    private GotenbergClient $client;

    public function __construct(string $orientation = 'P', string $unit = 'mm', string $format = 'A4', bool $unicode = true, string $encoding = 'UTF-8', bool $diskCache = false)
    {
        $this->landscape = strtoupper($orientation) === 'L';
        $this->client = new GotenbergClient();
    }

    // ── Setup calls usados pelos factories — sem efeito na renderização
    //    via HTML/Chromium, mas mantidos para não quebrar os call sites. ──

    public function SetCreator(string $creator): void {}
    public function SetAuthor(string $author): void {}

    public function SetTitle(string $title): void
    {
        $this->title = $title;
    }

    public function SetSubject(string $subject): void {}
    public function setPrintHeader(bool $print): void {}
    public function setPrintFooter(bool $print): void {}
    public function setHeaderFont(array $font): void {}
    public function SetHeaderMargin(float $margin): void {}
    public function SetFooterMargin(float $margin): void {}
    public function setFooterFont(array $font): void {}
    public function setFooterData($textColor = [0, 0, 0], $lineColor = [0, 0, 0]): void {}
    public function setImageScale(float $scale): void {}
    public function SetFont(string $family, string $style = '', $size = null): void {}
    public function AddPage(): void {}

    public function SetHeaderData($logo = '', $logoWidth = 0, $title = '', $string = ''): void
    {
        $this->title = $title !== '' ? $title : $this->title;
    }

    public function SetMargins(float $left, float $top, ?float $right = null, bool $keepMargins = false): void
    {
        $this->marginLeftMm = $left;
        $this->marginTopMm = $top;
        $this->marginRightMm = $right ?? $left;
    }

    public function SetAutoPageBreak(bool $auto, float $margin = 0): void
    {
        $this->marginBottomMm = $margin > 0 ? $margin : $this->marginBottomMm;
    }

    // ── Conteúdo ──────────────────────────────────────────────────────────

    public function writeHTML(string $html, bool $ln = true, bool $fill = false, bool $reseth = true, bool $cell = false, string $align = ''): void
    {
        $this->htmlBuffer .= $html;
    }

    /**
     * @param string $name Nome do arquivo (usado apenas quando $dest = 'F').
     * @param string $dest 'S' = retorna os bytes do PDF; 'F' = grava em disco e retorna true; 'I'/'D' = retorna os bytes (equivalente a 'S').
     * @return string|bool
     */
    public function Output(string $name = 'document.pdf', string $dest = 'I')
    {
        $document = $this->wrapDocument($this->htmlBuffer, $this->title);

        $mmToIn = 0.0393701;
        $pdfBytes = $this->client->htmlToPdf($document, [
            'landscape' => $this->landscape,
            'paperWidthInches' => $this->landscape ? 11.69 : 8.27,
            'paperHeightInches' => $this->landscape ? 8.27 : 11.69,
            'marginTopInches' => $this->marginTopMm * $mmToIn,
            'marginBottomInches' => $this->marginBottomMm * $mmToIn,
            'marginLeftInches' => $this->marginLeftMm * $mmToIn,
            'marginRightInches' => $this->marginRightMm * $mmToIn,
        ]);

        if ($dest === 'F') {
            file_put_contents($name, $pdfBytes);
            return true;
        }

        return $pdfBytes;
    }

    private function wrapDocument(string $html, string $title): string
    {
        // Os content builders já emitem tabelas/estilos formatados para o
        // corpo do documento — só garantimos aqui um <html> válido com
        // charset UTF-8 (essencial para acentuação em pt-BR) caso o HTML
        // recebido não seja um documento completo.
        if (stripos($html, '<html') !== false) {
            return $html;
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;}</style>'
            . '</head><body>' . $html . '</body></html>';
    }
}

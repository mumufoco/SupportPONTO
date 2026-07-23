<?php

namespace App\Services\Excel;

use App\Services\Reports\ReportFilterRenderer;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Drawing;

class ExcelSheetFormatter
{
    // Paleta corporativa (ARGB para PhpSpreadsheet)
    private const NAVY   = 'FF1B3A6B';
    private const BLUE   = 'FF2E86AB';
    private const LIGHT  = 'FFEBF3FA';
    private const STRIPE = 'FFF7FBFF';
    private const WHITE  = 'FFFFFFFF';
    private const GREEN  = 'FF1A6B3A';
    private const RED    = 'FF8B1A1A';
    private const YELLOW = 'FF7A5C00';
    private const MUTED  = 'FF5A6A7E';
    private const BORDER = 'FFC8DCF0';

    public function __construct(
        private readonly string               $companyName,
        private readonly ReportFilterRenderer $filterRenderer,
        private readonly string               $companyCnpj  = '',
        private readonly ?string              $logoPath      = null,
    ) {}

    /**
     * Cria cabeçalho corporativo completo na planilha.
     * Retorna a próxima linha disponível após o cabeçalho.
     */
    public function createHeader($sheet, string $reportTitle): int
    {
        // Linha 1 — Nome da empresa
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', $this->companyName);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Linha 2 — CNPJ (se disponível)
        $row = 2;
        if ($this->companyCnpj) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", 'CNPJ: ' . $this->companyCnpj);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['size' => 9, 'color' => ['argb' => self::MUTED]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(14);
            $row++;
        }

        // Separador
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NAVY]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(3);
        $row++;

        // Título do relatório
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", $reportTitle);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::LIGHT]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        // Data de geração
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'Gerado em: ' . date('d/m/Y \à\s H:i:s'));
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['size' => 8, 'italic' => true, 'color' => ['argb' => self::MUTED]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(13);
        $row++;

        // Linha separadora inferior
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BLUE]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(2);
        $row++;

        return $row + 1; // linha em branco antes dos filtros
    }

    /**
     * Renderiza os filtros aplicados e retorna próxima linha.
     */
    public function renderFilters($sheet, array $filters, int $startRow): int
    {
        $skipKeys = ['limit', 'employee_ids', 'department_restriction_applied'];
        $labels = [
            'start_date'  => 'Data Inicial',
            'end_date'    => 'Data Final',
            'department'  => 'Departamento',
            'employee_id' => 'Colaborador ID',
            'status'      => 'Status',
        ];

        $row = $startRow;
        $hasFilters = false;
        foreach ($filters as $key => $value) {
            if (in_array($key, $skipKeys, true) || $value === null || $value === '') continue;
            if (is_array($value)) $value = implode(', ', array_map('strval', $value));
            $hasFilters = true;

            $label = $labels[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", (string) $value);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 8, 'color' => ['argb' => self::NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->getStyle("B{$row}")->applyFromArray([
                'font' => ['size' => 8],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(13);
            $row++;
        }

        if ($hasFilters) {
            $row++; // linha em branco após filtros
        }
        return $row;
    }

    /**
     * Cria linha de cabeçalho da tabela de dados.
     */
    public function createTableHeader($sheet, array $headers, int $row): void
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $col++;
        }
        $this->styleTableHeader($sheet, $row, count($headers));
    }

    /**
     * Aplica estilo corporativo no cabeçalho da tabela.
     */
    public function styleTableHeader($sheet, int $row, int $colCount): void
    {
        $lastCol = chr(64 + $colCount);
        $range   = "A{$row}:{$lastCol}{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font'      => [
                'bold'  => true,
                'size'  => 9,
                'color' => ['argb' => self::WHITE],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::NAVY],
            ],
            'borders'   => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF8FA8C8'],
                ],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    /**
     * Estiliza uma linha de dado (com zebra striping).
     */
    public function styleDataRow($sheet, int $row, int $colCount, bool $odd): void
    {
        $lastCol = chr(64 + $colCount);
        $range   = "A{$row}:{$lastCol}{$row}";
        $bg      = $odd ? self::STRIPE : self::WHITE;

        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['size' => 8],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::BORDER]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(14);
    }

    /**
     * Estiliza célula de valor positivo (verde).
     */
    public function stylePositive($sheet, string $cell): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::GREEN]],
        ]);
    }

    /**
     * Estiliza célula de valor negativo (vermelho).
     */
    public function styleNegative($sheet, string $cell): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::RED]],
        ]);
    }

    /**
     * Cria linha de totais/rodapé da tabela.
     */
    public function createTotalsRow($sheet, array $values, int $row, int $colCount): void
    {
        $lastCol = chr(64 + $colCount);
        $col = 'A';
        foreach ($values as $value) {
            $sheet->setCellValue("{$col}{$row}", $value);
            $col++;
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => self::NAVY]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::LIGHT]],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . ltrim(self::NAVY, 'FF')]],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . ltrim(self::NAVY, 'FF')]],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16);
    }

    /**
     * Auto-dimensiona colunas de A até $lastCol.
     */
    public function autoSizeColumns($sheet, string $lastCol): void
    {
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Congela o painel na linha de dados (após o cabeçalho da tabela).
     */
    public function freezeHeaderRow($sheet, int $headerRow): void
    {
        $sheet->freezePane('A' . ($headerRow + 1));
    }

    // ── Mantém compatibilidade com chamadas antigas ───────────────────────────
    /** @deprecated Use createHeader() */
    public function applySpreadsheetHeader($sheet, string $title): void
    {
        $this->filterRenderer->applySpreadsheetHeader($sheet, $title);
    }
}

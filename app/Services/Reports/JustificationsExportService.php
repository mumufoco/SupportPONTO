<?php

namespace App\Services\Reports;

use App\Services\Pdf\PdfDocumentFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Exportação da tela de Justificativas (reports/justifications.php) — mesma
 * fonte de dados já calculada por ReportViewService::getJustificationsViewData()
 * ($justifications), para que o arquivo bata exatamente com o que é exibido
 * na tela (mesma restrição de departamento do gestor).
 */
class JustificationsExportService
{
    private const E_NAVY   = '1B3A6B';
    private const E_LIGHT  = 'EBF3FA';
    private const E_WHITE  = 'FFFFFF';
    private const E_TEXT   = '1A2636';
    private const E_GREEN  = '1A6B3A';
    private const E_RED    = 'C0392B';
    private const E_YELLOW = 'F59E0B';

    private const C_NAVY   = '#1B3A6B';
    private const C_BLUE   = '#2E86AB';
    private const C_LIGHT  = '#EBF3FA';
    private const C_STRIPE = '#F7FBFF';
    private const C_BORDER = '#C8DCF0';
    private const C_TEXT   = '#1A2636';
    private const C_GREEN  = '#1A6B3A';
    private const C_RED    = '#8B1A1A';
    private const C_YELLOW = '#7A5C00';

    public function __construct()
    {
        helper(['format', 'datetime']);
    }

    private function companySettings(): array
    {
        try {
            $db = \Config\Database::connect();
            $rows = $db->table('settings')
                ->whereIn('key', ['company_name', 'company_cnpj', 'company_logo', 'logo_path'])
                ->get()->getResultObject();
            $cfg = [];
            foreach ($rows as $r) {
                $cfg[$r->key] = $r->value;
            }
            return $cfg;
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolvedLogoPath(array $cfg): ?string
    {
        $rel = $cfg['company_logo'] ?? $cfg['logo_path'] ?? null;
        if (! $rel) {
            return null;
        }
        $abs = FCPATH . $rel;
        return file_exists($abs) ? $abs : null;
    }

    private function periodLabel(string $month, ?string $department): string
    {
        $label = format_month_year_br($month . '-01') ?: $month;
        return $department ? $label . ' — ' . $department : $label;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'aprovado' => 'Aprovada',
            'rejeitado' => 'Rejeitada',
            default => 'Pendente',
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════

    public function buildPdf(array $justifications, string $month, ?string $department): array
    {
        $cfg = $this->companySettings();
        $logo = $this->resolvedLogoPath($cfg);
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj = $cfg['company_cnpj'] ?? '';

        $factory = new PdfDocumentFactory($company, $cnpj, $logo);
        $pdf = $factory->create('Relatório de Justificativas', 'L');

        $html = $this->pdfHtml($justifications, $month, $department, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'justificativas_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $month) . '.pdf';
        return ['content' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    private function pdfHtml(array $justifications, string $month, ?string $department, PdfDocumentFactory $factory): string
    {
        $h = '<style>';
        $h .= 'body{font-family:helvetica;font-size:8pt;color:' . self::C_TEXT . ';}';
        $h .= 'h2{font-size:9pt;font-weight:bold;color:' . self::C_NAVY . ';margin:6px 0 2px;background:' . self::C_LIGHT . ';padding:3px 6px;border-left:3px solid ' . self::C_BLUE . ';}';
        $h .= 'table{border-collapse:collapse;width:100%;margin-bottom:6px;}';
        $h .= 'th{background:' . self::C_NAVY . ';color:#fff;font-size:7pt;padding:3px 4px;text-align:center;font-weight:bold;}';
        $h .= 'td{font-size:7.5pt;padding:2.5px 4px;border-bottom:0.3px solid ' . self::C_BORDER . ';}';
        $h .= '.stripe{background:' . self::C_STRIPE . ';}';
        $h .= '.ok{color:' . self::C_GREEN . ';font-weight:bold;}';
        $h .= '.bad{color:' . self::C_RED . ';font-weight:bold;}';
        $h .= '.warn{color:' . self::C_YELLOW . ';font-weight:bold;}';
        $h .= '</style>';

        $h .= $factory->reportHeader('Relatório de Justificativas', $this->periodLabel($month, $department));

        $total = count($justifications);
        $pending = count(array_filter($justifications, static fn($j): bool => ($j->status ?? '') === 'pendente'));
        $approved = count(array_filter($justifications, static fn($j): bool => ($j->status ?? '') === 'aprovado'));
        $rejected = count(array_filter($justifications, static fn($j): bool => ($j->status ?? '') === 'rejeitado'));

        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Total', (string) $total, self::C_NAVY],
            ['Pendentes', (string) $pending, self::C_YELLOW],
            ['Aprovadas', (string) $approved, self::C_GREEN],
            ['Rejeitadas', (string) $rejected, self::C_RED],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        $h .= '<h2>Justificativas</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $h .= '<thead><tr>';
        $h .= '<th width="10%">Data</th><th width="20%">Colaborador</th><th width="15%">Departamento</th><th width="35%">Motivo</th><th width="10%">Status</th><th width="10%">Solicitada em</th>';
        $h .= '</tr></thead><tbody>';

        foreach ($justifications as $i => $item) {
            $status = (string) ($item->status ?? 'pendente');
            $cls = match ($status) {
                'aprovado' => 'ok',
                'rejeitado' => 'bad',
                default => 'warn',
            };
            $rowCls = $i % 2 === 0 ? '' : 'stripe';

            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td align="center">' . esc(format_date_br((string) ($item->justification_date ?? $item->date ?? ''))) . '</td>';
            $h .= '<td>' . esc((string) ($item->employee_name ?? '')) . '</td>';
            $h .= '<td>' . esc((string) ($item->employee_department ?? '')) . '</td>';
            $h .= '<td>' . esc((string) ($item->reason ?? $item->description ?? 'Sem descrição')) . '</td>';
            $h .= '<td align="center" class="' . $cls . '">' . esc($this->statusLabel($status)) . '</td>';
            $h .= '<td align="center">' . esc(format_datetime_br((string) ($item->created_at ?? ''), false)) . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';

        return $h;
    }

    // ══════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════

    public function buildExcel(array $justifications, string $month, ?string $department): array
    {
        $cfg = $this->companySettings();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Relatório de Justificativas')
            ->setSubject('Justificativas')
            ->setCreator($cfg['company_name'] ?? 'SupportPONTO')
            ->setCompany($cfg['company_name'] ?? 'SupportPONTO')
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Justificativas');

        $row = 1;
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, $company);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
        ]);
        $row += 2;

        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, 'RELATÓRIO DE JUSTIFICATIVAS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_LIGHT]],
        ]);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'Período:');
        $sheet->setCellValue('B' . $row, $this->periodLabel($month, $department));
        $sheet->setCellValue('D' . $row, 'Gerado em:');
        $sheet->setCellValue('E' . $row, date('d/m/Y H:i'));
        foreach (['A' . $row, 'D' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(9);
        }
        $row += 2;

        $headers = ['Data', 'Colaborador', 'Departamento', 'Motivo', 'Status', 'Solicitada em'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];
        $widths = [12, 26, 18, 45, 14, 18];
        $headerRow = $row;
        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}{$headerRow}", $headers[$i]);
            $sheet->getColumnDimension($col)->setWidth($widths[$i]);
        }
        $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $sheet->setAutoFilter("A{$headerRow}:F{$headerRow}");
        $sheet->freezePane('A' . ($headerRow + 1));

        $dataRow = $headerRow + 1;
        foreach ($justifications as $item) {
            $status = (string) ($item->status ?? 'pendente');

            $sheet->fromArray([
                format_date_br((string) ($item->justification_date ?? $item->date ?? '')),
                $item->employee_name ?? '',
                $item->employee_department ?? '',
                $item->reason ?? $item->description ?? 'Sem descrição',
                $this->statusLabel($status),
                format_datetime_br((string) ($item->created_at ?? ''), false),
            ], null, "A{$dataRow}");

            $bg = $dataRow % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            $sheet->getStyle("A{$dataRow}:F{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);
            $statusColor = match ($status) {
                'aprovado' => self::E_GREEN,
                'rejeitado' => self::E_RED,
                default => self::E_YELLOW,
            };
            $sheet->getStyle("E{$dataRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF' . $statusColor]],
            ]);

            $dataRow++;
        }

        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:F" . ($dataRow - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }

        $filename = 'justificativas_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $month) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ['content' => ob_get_clean(), 'filename' => $filename];
    }

    // ══════════════════════════════════════════════════════════════════════
    // CSV
    // ══════════════════════════════════════════════════════════════════════

    public function buildCsv(array $justifications): array
    {
        $lines = [$this->csvLine(['Data', 'Colaborador', 'Departamento', 'Motivo', 'Status', 'Solicitada em'])];

        foreach ($justifications as $item) {
            $status = (string) ($item->status ?? 'pendente');

            $lines[] = $this->csvLine([
                format_date_br((string) ($item->justification_date ?? $item->date ?? '')),
                $item->employee_name ?? '',
                $item->employee_department ?? '',
                $item->reason ?? $item->description ?? 'Sem descrição',
                $this->statusLabel($status),
                format_datetime_br((string) ($item->created_at ?? ''), false),
            ]);
        }

        $filename = 'justificativas_' . date('Ymd') . '.csv';

        return ['content' => "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 'filename' => $filename];
    }

    private function csvLine(array $fields): string
    {
        return implode(';', array_map(static function ($field): string {
            return '"' . str_replace('"', '""', (string) $field) . '"';
        }, $fields));
    }
}

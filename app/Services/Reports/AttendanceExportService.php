<?php

namespace App\Services\Reports;

use App\Services\Pdf\PdfDocumentFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Exportação da tela de Assiduidade (reports/attendance.php) — mesma fonte de
 * dados já calculada por ReportViewService::getAttendanceViewData() ($attendanceData),
 * para que o arquivo bata exatamente com o que é exibido na tela (mesma
 * restrição de departamento do gestor já aplicada em getEmployeeScope()).
 */
class AttendanceExportService
{
    private const E_NAVY   = '1B3A6B';
    private const E_LIGHT  = 'EBF3FA';
    private const E_WHITE  = 'FFFFFF';
    private const E_TEXT   = '1A2636';
    private const E_MUTED  = '5A6A7E';
    private const E_GREEN  = '1A6B3A';
    private const E_RED    = 'C0392B';

    private const C_NAVY   = '#1B3A6B';
    private const C_BLUE   = '#2E86AB';
    private const C_LIGHT  = '#EBF3FA';
    private const C_STRIPE = '#F7FBFF';
    private const C_BORDER = '#C8DCF0';
    private const C_TEXT   = '#1A2636';
    private const C_MUTED  = '#5A6A7E';
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

    // ══════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════

    public function buildPdf(array $attendanceData, string $month, ?string $department): array
    {
        $cfg = $this->companySettings();
        $logo = $this->resolvedLogoPath($cfg);
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj = $cfg['company_cnpj'] ?? '';

        $factory = new PdfDocumentFactory($company, $cnpj, $logo);
        $pdf = $factory->create('Assiduidade e Frequência', 'L');

        $html = $this->pdfHtml($attendanceData, $month, $department, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'assiduidade_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $month) . '.pdf';
        return ['content' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    private function pdfHtml(array $attendanceData, string $month, ?string $department, PdfDocumentFactory $factory): string
    {
        $h = '<style>';
        $h .= 'body{font-family:helvetica;font-size:8pt;color:' . self::C_TEXT . ';}';
        $h .= 'h2{font-size:9pt;font-weight:bold;color:' . self::C_NAVY . ';margin:6px 0 2px;background:' . self::C_LIGHT . ';padding:3px 6px;border-left:3px solid ' . self::C_BLUE . ';}';
        $h .= 'table{border-collapse:collapse;width:100%;margin-bottom:6px;}';
        $h .= 'th{background:' . self::C_NAVY . ';color:#fff;font-size:7pt;padding:3px 4px;text-align:center;font-weight:bold;}';
        $h .= 'td{font-size:7.5pt;padding:2.5px 4px;border-bottom:0.3px solid ' . self::C_BORDER . ';}';
        $h .= '.stripe{background:' . self::C_STRIPE . ';}';
        $h .= '.warn{color:' . self::C_YELLOW . ';font-weight:bold;}';
        $h .= '</style>';

        $h .= $factory->reportHeader('Assiduidade e Frequência', $this->periodLabel($month, $department));

        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Colaboradores', (string) count($attendanceData), self::C_NAVY],
            ['Total de Atrasos', (string) array_sum(array_column($attendanceData, 'late_arrivals')), self::C_YELLOW],
            ['Marcações Faltantes', (string) array_sum(array_column($attendanceData, 'missing_punches')), self::C_YELLOW],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        $h .= '<h2>Assiduidade por Colaborador</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $cols = [
            ['Colaborador', '22%'], ['Departamento', '15%'], ['Dias', '8%'], ['Horas', '10%'],
            ['Previsto', '10%'], ['Saldo', '10%'], ['Atrasos', '8%'], ['Faltantes', '9%'], ['Assiduidade', '8%'],
        ];
        $h .= '<thead><tr>';
        foreach ($cols as [$lbl, $w]) {
            $h .= '<th width="' . $w . '">' . $lbl . '</th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($attendanceData as $i => $item) {
            $rowCls = $i % 2 === 0 ? '' : 'stripe';
            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td>' . esc((string) ($item['employee']->name ?? '—')) . '</td>';
            $h .= '<td>' . esc((string) ($item['employee']->department ?? '—')) . '</td>';
            $h .= '<td align="center">' . esc((string) ($item['days_worked'] ?? 0)) . '</td>';
            $h .= '<td align="center">' . esc(format_hours((float) ($item['hours_worked'] ?? 0))) . '</td>';
            $h .= '<td align="center">' . esc(format_hours((float) ($item['expected_hours'] ?? 0))) . '</td>';
            $h .= '<td align="center">' . esc(format_hours((float) ($item['balance'] ?? 0), true)) . '</td>';
            $h .= '<td align="center">' . esc((string) ($item['late_arrivals'] ?? 0)) . '</td>';
            $h .= '<td align="center">' . esc((string) ($item['missing_punches'] ?? 0)) . '</td>';
            $h .= '<td align="center" style="font-weight:bold;">' . esc(format_percentage((float) ($item['attendance_rate'] ?? 0), 1)) . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';

        return $h;
    }

    // ══════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════

    public function buildExcel(array $attendanceData, string $month, ?string $department): array
    {
        $cfg = $this->companySettings();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Assiduidade e Frequência')
            ->setSubject('Relatório de Assiduidade')
            ->setCreator($cfg['company_name'] ?? 'SupportPONTO')
            ->setCompany($cfg['company_name'] ?? 'SupportPONTO')
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assiduidade');

        $row = 1;
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, $company);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
        ]);
        $row += 2;

        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, 'ASSIDUIDADE E FREQUÊNCIA');
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

        $headers = ['Colaborador', 'Departamento', 'Dias Trabalhados', 'Horas', 'Previsto', 'Saldo', 'Atrasos', 'Marcações Faltantes', 'Assiduidade'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $widths = [26, 18, 14, 10, 10, 10, 10, 16, 12];
        $headerRow = $row;
        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}{$headerRow}", $headers[$i]);
            $sheet->getColumnDimension($col)->setWidth($widths[$i]);
        }
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $sheet->setAutoFilter("A{$headerRow}:I{$headerRow}");
        $sheet->freezePane('A' . ($headerRow + 1));

        $dataRow = $headerRow + 1;
        foreach ($attendanceData as $item) {
            $balance = (float) ($item['balance'] ?? 0);

            $sheet->fromArray([
                $item['employee']->name ?? '—',
                $item['employee']->department ?? '—',
                (int) ($item['days_worked'] ?? 0),
                format_hours((float) ($item['hours_worked'] ?? 0)),
                format_hours((float) ($item['expected_hours'] ?? 0)),
                format_hours($balance, true),
                (int) ($item['late_arrivals'] ?? 0),
                (int) ($item['missing_punches'] ?? 0),
                format_percentage((float) ($item['attendance_rate'] ?? 0), 1),
            ], null, "A{$dataRow}");

            $bg = $dataRow % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            $sheet->getStyle("A{$dataRow}:I{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);
            if ($balance < 0) {
                $sheet->getStyle("F{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_RED]],
                ]);
            } elseif ($balance > 0) {
                $sheet->getStyle("F{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_GREEN]],
                ]);
            }

            $dataRow++;
        }

        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:I" . ($dataRow - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }

        $filename = 'assiduidade_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $month) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ['content' => ob_get_clean(), 'filename' => $filename];
    }

    // ══════════════════════════════════════════════════════════════════════
    // CSV
    // ══════════════════════════════════════════════════════════════════════

    public function buildCsv(array $attendanceData): array
    {
        $lines = [$this->csvLine(['Colaborador', 'Departamento', 'Dias Trabalhados', 'Horas', 'Previsto', 'Saldo', 'Atrasos', 'Marcações Faltantes', 'Assiduidade'])];

        foreach ($attendanceData as $item) {
            $lines[] = $this->csvLine([
                $item['employee']->name ?? '—',
                $item['employee']->department ?? '—',
                (string) ($item['days_worked'] ?? 0),
                format_hours((float) ($item['hours_worked'] ?? 0)),
                format_hours((float) ($item['expected_hours'] ?? 0)),
                format_hours((float) ($item['balance'] ?? 0), true),
                (string) ($item['late_arrivals'] ?? 0),
                (string) ($item['missing_punches'] ?? 0),
                format_percentage((float) ($item['attendance_rate'] ?? 0), 1),
            ]);
        }

        $filename = 'assiduidade_' . date('Ymd') . '.csv';

        return ['content' => "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 'filename' => $filename];
    }

    private function csvLine(array $fields): string
    {
        return implode(';', array_map(static function ($field): string {
            return '"' . str_replace('"', '""', (string) $field) . '"';
        }, $fields));
    }
}

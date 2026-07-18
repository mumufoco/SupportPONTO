<?php

namespace App\Services\Reports;

use App\Services\Pdf\PdfDocumentFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Exportação do espelho consolidado de ponto (reports/timesheet.php) — mesma
 * fonte de dados já calculada por TimesheetService::generateMonthlyTimesheet()
 * (daily_records/punches/summary), para que o PDF/Excel/CSV bata exatamente
 * com o que é exibido na tela, incluindo as 4 colunas de marcação (Entrada,
 * Início Intervalo, Fim Intervalo, Saída).
 */
class MonthlyTimesheetExportService
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
    private const C_GREEN  = '#1A6B3A';
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

    private function maskCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) === 11) {
            return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
        }
        return $cpf ?: '—';
    }

    private function periodLabel(string $month): string
    {
        return format_month_year_br($month . '-01') ?: $month;
    }

    /** @return array<string, list<string>> */
    private function punchColumns(array $punches): array
    {
        $cols = ['entrada' => [], 'intervalo_inicio' => [], 'intervalo_fim' => [], 'saida' => []];
        foreach ($punches as $p) {
            $type = (string) ($p['type'] ?? '');
            if (array_key_exists($type, $cols)) {
                $cols[$type][] = format_time((string) ($p['time'] ?? ''), false);
            }
        }
        return $cols;
    }

    private function validationLabel(array $record): array
    {
        $validation = $record['validation'] ?? [];
        $isValid = (bool) ($validation['valid'] ?? false);
        $message = $validation['message'] ?? ($isValid ? 'OK' : 'Inconsistente');
        return [$isValid, (string) $message];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════

    public function buildPdf(object $employee, array $dailyRecords, array $summary, string $month): array
    {
        $cfg = $this->companySettings();
        $logo = $this->resolvedLogoPath($cfg);
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj = $cfg['company_cnpj'] ?? '';

        $factory = new PdfDocumentFactory($company, $cnpj, $logo);
        $pdf = $factory->create('Espelho Consolidado de Ponto — ' . ($employee->name ?? ''), 'L');

        $html = $this->pdfHtml($employee, $dailyRecords, $summary, $month, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'espelho_consolidado_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.pdf';
        return ['content' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    private function pdfHtml(object $emp, array $dailyRecords, array $summary, string $month, PdfDocumentFactory $factory): string
    {
        $h = '<style>';
        $h .= 'body{font-family:helvetica;font-size:8pt;color:' . self::C_TEXT . ';}';
        $h .= 'h2{font-size:9pt;font-weight:bold;color:' . self::C_NAVY . ';margin:6px 0 2px;background:' . self::C_LIGHT . ';padding:3px 6px;border-left:3px solid ' . self::C_BLUE . ';}';
        $h .= 'table{border-collapse:collapse;width:100%;margin-bottom:6px;}';
        $h .= 'th{background:' . self::C_NAVY . ';color:#fff;font-size:7pt;padding:3px 4px;text-align:center;font-weight:bold;}';
        $h .= 'td{font-size:7.5pt;padding:2.5px 4px;border-bottom:0.3px solid ' . self::C_BORDER . ';}';
        $h .= '.stripe{background:' . self::C_STRIPE . ';}';
        $h .= '.ok{color:' . self::C_GREEN . ';font-weight:bold;}';
        $h .= '.warn{color:' . self::C_YELLOW . ';font-weight:bold;}';
        $h .= '.muted{color:' . self::C_MUTED . ';}';
        $h .= '</style>';

        $h .= $factory->reportHeader('Espelho Consolidado de Ponto', $this->periodLabel($month));

        $h .= '<h2>Dados do Colaborador</h2>';
        $h .= '<table border="0" cellpadding="3" cellspacing="0" style="width:100%;margin-bottom:8px;border:0.5px solid ' . self::C_BORDER . ';">';
        $fields = [
            ['Nome completo', esc($emp->name ?? '—')],
            ['CPF', esc($this->maskCpf($emp->cpf ?? ''))],
            ['Departamento', esc($emp->department ?? '—')],
        ];
        $h .= '<tr>';
        foreach ($fields as [$lbl, $val]) {
            $h .= '<td width="15%" style="color:' . self::C_MUTED . ';font-size:7pt;">' . $lbl . '</td>';
            $h .= '<td width="18%" style="font-weight:bold;font-size:8pt;">' . $val . '</td>';
        }
        $h .= '</tr>';
        $h .= '</table>';

        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Horas Trabalhadas', number_format((float) ($summary['total_hours'] ?? 0), 2) . 'h', self::C_NAVY],
            ['Horas Previstas', number_format((float) ($summary['expected_hours'] ?? 0), 2) . 'h', self::C_BLUE],
            ['Saldo', ($summary['balance'] ?? 0) >= 0 ? '+' . number_format((float) $summary['balance'], 2) . 'h' : number_format((float) $summary['balance'], 2) . 'h', ($summary['balance'] ?? 0) >= 0 ? self::C_GREEN : self::C_YELLOW],
            ['Dias com Jornada', (string) ($summary['days_worked'] ?? 0), self::C_NAVY],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        $h .= '<h2>Marcações Diárias</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $cols = [
            ['Data', '9%'], ['Dia', '6%'], ['Entrada', '8%'], ['Início Int.', '8%'], ['Fim Int.', '8%'], ['Saída', '8%'],
            ['Horas', '9%'], ['Previsto', '9%'], ['Saldo', '9%'], ['Justif.', '8%'], ['Validação', '18%'],
        ];
        $h .= '<thead><tr>';
        foreach ($cols as [$lbl, $w]) {
            $h .= '<th width="' . $w . '">' . $lbl . '</th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($dailyRecords as $i => $record) {
            $punchCols = $this->punchColumns($record['punches'] ?? []);
            [$isValid, $validationMessage] = $this->validationLabel($record);
            $justificationCount = is_array($record['justifications'] ?? null) ? count($record['justifications']) : 0;
            $rowCls = $i % 2 === 0 ? '' : 'stripe';

            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td align="center">' . esc(format_date_br((string) $record['date'])) . '</td>';
            $h .= '<td align="center">' . esc(get_day_of_week_br((string) $record['date'], true)) . '</td>';
            foreach (['entrada', 'intervalo_inicio', 'intervalo_fim', 'saida'] as $col) {
                $times = $punchCols[$col];
                $h .= '<td align="center">' . ($times === [] ? '<span class="muted">—</span>' : esc(implode(', ', $times))) . '</td>';
            }
            $h .= '<td align="center" style="font-weight:bold;">' . esc(format_hours((float) ($record['hours_worked'] ?? 0))) . '</td>';
            $h .= '<td align="center">' . esc(format_hours((float) ($record['expected_hours'] ?? 0))) . '</td>';
            $h .= '<td align="center">' . esc(format_hours((float) ($record['balance'] ?? 0), true)) . '</td>';
            $h .= '<td align="center">' . esc((string) $justificationCount) . '</td>';
            $h .= '<td align="center">' . ($isValid ? '<span class="ok">' . esc($validationMessage) . '</span>' : '<span class="warn">' . esc($validationMessage) . '</span>') . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';

        return $h;
    }

    // ══════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════

    public function buildExcel(object $employee, array $dailyRecords, array $summary, string $month): array
    {
        $cfg = $this->companySettings();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Espelho Consolidado de Ponto — ' . ($employee->name ?? ''))
            ->setSubject('Espelho Consolidado de Ponto')
            ->setCreator($cfg['company_name'] ?? 'SupportPONTO')
            ->setCompany($cfg['company_name'] ?? 'SupportPONTO')
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Espelho Consolidado');

        $row = 1;
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->setCellValue('A' . $row, $company);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
        ]);
        $row += 2;

        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->setCellValue('A' . $row, 'ESPELHO CONSOLIDADO DE PONTO — ' . strtoupper($employee->name ?? ''));
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_LIGHT]],
        ]);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'CPF:');
        $sheet->setCellValue('B' . $row, $this->maskCpf($employee->cpf ?? ''));
        $sheet->setCellValue('D' . $row, 'Departamento:');
        $sheet->setCellValue('E' . $row, $employee->department ?? '—');
        $row++;
        $sheet->setCellValue('A' . $row, 'Período:');
        $sheet->setCellValue('B' . $row, $this->periodLabel($month));
        $sheet->setCellValue('D' . $row, 'Gerado em:');
        $sheet->setCellValue('E' . $row, date('d/m/Y H:i'));
        foreach (['A' . ($row - 1), 'D' . ($row - 1), 'A' . $row, 'D' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(9);
        }
        $row += 2;

        $sheet->setCellValue('A' . $row, 'Horas Trabalhadas:');
        $sheet->setCellValue('B' . $row, number_format((float) ($summary['total_hours'] ?? 0), 2) . 'h');
        $sheet->setCellValue('D' . $row, 'Horas Previstas:');
        $sheet->setCellValue('E' . $row, number_format((float) ($summary['expected_hours'] ?? 0), 2) . 'h');
        $sheet->setCellValue('G' . $row, 'Saldo:');
        $sheet->setCellValue('H' . $row, ($summary['balance'] ?? 0) >= 0 ? '+' . number_format((float) $summary['balance'], 2) . 'h' : number_format((float) $summary['balance'], 2) . 'h');
        foreach (['A' . $row, 'D' . $row, 'G' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(9);
        }
        $row += 2;

        $headers = ['Data', 'Dia', 'Entrada', 'Início Intervalo', 'Fim Intervalo', 'Saída', 'Horas', 'Previsto', 'Saldo', 'Justificativas', 'Validação'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
        $widths = [12, 10, 10, 15, 13, 10, 10, 10, 10, 13, 16];
        $headerRow = $row;
        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}{$headerRow}", $headers[$i]);
            $sheet->getColumnDimension($col)->setWidth($widths[$i]);
        }
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $sheet->setAutoFilter("A{$headerRow}:K{$headerRow}");
        $sheet->freezePane('A' . ($headerRow + 1));

        $dataRow = $headerRow + 1;
        foreach ($dailyRecords as $record) {
            $punchCols = $this->punchColumns($record['punches'] ?? []);
            [$isValid, $validationMessage] = $this->validationLabel($record);
            $justificationCount = is_array($record['justifications'] ?? null) ? count($record['justifications']) : 0;

            $sheet->fromArray([
                format_date_br((string) $record['date']),
                get_day_of_week_br((string) $record['date'], true),
                implode(', ', $punchCols['entrada']) ?: '—',
                implode(', ', $punchCols['intervalo_inicio']) ?: '—',
                implode(', ', $punchCols['intervalo_fim']) ?: '—',
                implode(', ', $punchCols['saida']) ?: '—',
                format_hours((float) ($record['hours_worked'] ?? 0)),
                format_hours((float) ($record['expected_hours'] ?? 0)),
                format_hours((float) ($record['balance'] ?? 0), true),
                $justificationCount,
                $validationMessage,
            ], null, "A{$dataRow}");

            $bg = $dataRow % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            $sheet->getStyle("A{$dataRow}:K{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);
            if (! $isValid) {
                $sheet->getStyle("K{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_RED]],
                ]);
            }

            $dataRow++;
        }

        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:K" . ($dataRow - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }

        $filename = 'espelho_consolidado_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ['content' => ob_get_clean(), 'filename' => $filename];
    }

    // ══════════════════════════════════════════════════════════════════════
    // CSV
    // ══════════════════════════════════════════════════════════════════════

    public function buildCsv(array $dailyRecords): array
    {
        $lines = [$this->csvLine(['Data', 'Dia', 'Entrada', 'Início Intervalo', 'Fim Intervalo', 'Saída', 'Horas', 'Previsto', 'Saldo', 'Justificativas', 'Validação'])];

        foreach ($dailyRecords as $record) {
            $punchCols = $this->punchColumns($record['punches'] ?? []);
            [, $validationMessage] = $this->validationLabel($record);
            $justificationCount = is_array($record['justifications'] ?? null) ? count($record['justifications']) : 0;

            $lines[] = $this->csvLine([
                format_date_br((string) $record['date']),
                get_day_of_week_br((string) $record['date'], true),
                implode(', ', $punchCols['entrada']) ?: '—',
                implode(', ', $punchCols['intervalo_inicio']) ?: '—',
                implode(', ', $punchCols['intervalo_fim']) ?: '—',
                implode(', ', $punchCols['saida']) ?: '—',
                format_hours((float) ($record['hours_worked'] ?? 0)),
                format_hours((float) ($record['expected_hours'] ?? 0)),
                format_hours((float) ($record['balance'] ?? 0), true),
                (string) $justificationCount,
                $validationMessage,
            ]);
        }

        $filename = 'espelho_consolidado_' . date('Ymd') . '.csv';

        return ['content' => "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 'filename' => $filename];
    }

    private function csvLine(array $fields): string
    {
        return implode(';', array_map(static function ($field): string {
            return '"' . str_replace('"', '""', (string) $field) . '"';
        }, $fields));
    }
}

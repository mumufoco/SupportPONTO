<?php

namespace App\Services\Timesheet;

use App\Services\Pdf\PdfDocumentFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Exportação do Espelho de Ponto (histórico de marcações) já filtrado por
 * período/tipo/método — ver TimesheetController::history() e
 * TimesheetReadService::getHistoryData(). Diferente de
 * TimesheetBalanceExportService (saldo consolidado por dia), aqui cada linha
 * é uma marcação individual, na mesma granularidade exibida na tela
 * /timesheet/history.
 */
class TimesheetHistoryExportService
{
    private const E_NAVY   = '1B3A6B';
    private const E_LIGHT  = 'EBF3FA';
    private const E_WHITE  = 'FFFFFF';
    private const E_TEXT   = '1A2636';
    private const E_MUTED  = '5A6A7E';
    private const E_GREEN  = '1A6B3A';
    private const E_YELLOW = 'F59E0B';

    private const C_NAVY   = '#1B3A6B';
    private const C_BLUE   = '#2E86AB';
    private const C_LIGHT  = '#EBF3FA';
    private const C_STRIPE = '#F7FBFF';
    private const C_BORDER = '#C8DCF0';
    private const C_TEXT   = '#1A2636';
    private const C_MUTED  = '#5A6A7E';
    private const C_GREEN  = '#1A6B3A';
    private const C_YELLOW = '#7A5C00';

    private function companySettings(): array
    {
        try {
            $db   = \Config\Database::connect();
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

    private function typeLabel(?string $type): string
    {
        return match ($type ?? '') {
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            'intervalo_inicio' => 'Início intervalo',
            'intervalo_fim' => 'Fim intervalo',
            default => ucfirst(str_replace('_', ' ', $type ?? '-')),
        };
    }

    private function methodLabel(?string $method): string
    {
        return match ($method ?? '') {
            'codigo' => 'Código',
            'cpf' => 'CPF',
            'facial' => 'Facial',
            'biometria' => 'Biometria',
            'qrcode' => 'QR Code',
            default => ucfirst($method ?? '-'),
        };
    }

    private function periodLabel(array $filters): string
    {
        $start = ! empty($filters['start_date']) ? date('d/m/Y', strtotime($filters['start_date'])) : '—';
        $end = ! empty($filters['end_date']) ? date('d/m/Y', strtotime($filters['end_date'])) : '—';
        return "{$start} a {$end}";
    }

    // ══════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════

    public function buildPdf(object $employee, array $punches, array $filters, array $summary): array
    {
        $cfg = $this->companySettings();
        $logo = $this->resolvedLogoPath($cfg);
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj = $cfg['company_cnpj'] ?? '';

        $factory = new PdfDocumentFactory($company, $cnpj, $logo);
        $pdf = $factory->create('Espelho de Ponto — ' . ($employee->name ?? ''), 'P');

        $html = $this->pdfHtml($employee, $punches, $filters, $summary, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'espelho_ponto_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.pdf';
        return ['content' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    private function pdfHtml(object $emp, array $punches, array $filters, array $summary, PdfDocumentFactory $factory): string
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
        $h .= '</style>';

        $h .= $factory->reportHeader('Espelho de Ponto', $this->periodLabel($filters));

        $h .= '<h2>Dados do Colaborador</h2>';
        $h .= '<table border="0" cellpadding="3" cellspacing="0" style="width:100%;margin-bottom:8px;border:0.5px solid ' . self::C_BORDER . ';">';
        $fields = [
            ['Nome completo', esc($emp->name ?? '—')],
            ['CPF', esc($this->maskCpf($emp->cpf ?? ''))],
            ['Departamento', esc($emp->department ?? '—')],
            ['Código único', esc($emp->unique_code ?? '—')],
        ];
        $chunks = array_chunk($fields, 2);
        foreach ($chunks as $i => $pair) {
            $bg = $i % 2 === 0 ? '#FFFFFF' : self::C_STRIPE;
            $h .= '<tr style="background:' . $bg . ';">';
            foreach ($pair as [$lbl, $val]) {
                $h .= '<td width="15%" style="color:' . self::C_MUTED . ';font-size:7pt;">' . $lbl . '</td>';
                $h .= '<td width="35%" style="font-weight:bold;font-size:8pt;">' . $val . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</table>';

        $h .= '<h2>Filtros aplicados</h2>';
        $h .= '<table border="0" cellpadding="3" cellspacing="0" style="width:100%;margin-bottom:8px;border:0.5px solid ' . self::C_BORDER . ';">';
        $h .= '<tr>';
        $h .= '<td width="25%" style="color:' . self::C_MUTED . ';font-size:7pt;">Período</td><td width="25%" style="font-weight:bold;font-size:8pt;">' . esc($this->periodLabel($filters)) . '</td>';
        $h .= '<td width="25%" style="color:' . self::C_MUTED . ';font-size:7pt;">Tipo</td><td width="25%" style="font-weight:bold;font-size:8pt;">' . esc(! empty($filters['type']) ? $this->typeLabel($filters['type']) : 'Todos') . '</td>';
        $h .= '</tr><tr>';
        $h .= '<td style="color:' . self::C_MUTED . ';font-size:7pt;">Método</td><td style="font-weight:bold;font-size:8pt;">' . esc(! empty($filters['method']) ? $this->methodLabel($filters['method']) : 'Todos') . '</td>';
        $h .= '<td style="color:' . self::C_MUTED . ';font-size:7pt;">Registros encontrados</td><td style="font-weight:bold;font-size:8pt;">' . count($punches) . '</td>';
        $h .= '</tr>';
        $h .= '</table>';

        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Registros', (string) count($punches), self::C_NAVY],
            ['Dias com marcação', (string) ($summary['total_days'] ?? 0), self::C_NAVY],
            ['Horas no período', number_format((float) ($summary['total_hours'] ?? 0), 2) . 'h', self::C_BLUE],
            ['Inconsistências', (string) ($summary['missing_punches'] ?? 0), ($summary['missing_punches'] ?? 0) > 0 ? self::C_YELLOW : self::C_NAVY],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        $h .= '<h2>Marcações</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $h .= '<thead><tr>';
        $h .= '<th width="15%">Data</th><th width="12%">Hora</th><th width="23%">Tipo</th><th width="20%">Método</th><th width="15%">Status</th><th width="15%">NSR</th>';
        $h .= '</tr></thead><tbody>';

        foreach ($punches as $i => $punch) {
            $punchTime = (string) ($punch->punch_time ?? '');
            $dateFmt = $punchTime ? date('d/m/Y', strtotime($punchTime)) : '—';
            $timeFmt = $punchTime ? date('H:i', strtotime($punchTime)) : '—';
            $isValid = filter_var($punch->is_valid ?? true, FILTER_VALIDATE_BOOLEAN);
            $rowCls = $i % 2 === 0 ? '' : 'stripe';

            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td align="center">' . esc($dateFmt) . '</td>';
            $h .= '<td align="center">' . esc($timeFmt) . '</td>';
            $h .= '<td align="center">' . esc($this->typeLabel($punch->punch_type ?? null)) . '</td>';
            $h .= '<td align="center">' . esc($this->methodLabel($punch->method ?? null)) . '</td>';
            $h .= '<td align="center">' . ($isValid ? '<span class="ok">Válido</span>' : '<span class="warn">Revisar</span>') . '</td>';
            $h .= '<td align="center">' . esc((string) ($punch->nsr ?? '—')) . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';

        return $h;
    }

    private function maskCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) === 11) {
            return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
        }
        return $cpf ?: '—';
    }

    // ══════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════

    public function buildExcel(object $employee, array $punches, array $filters, array $summary): array
    {
        $cfg = $this->companySettings();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Espelho de Ponto — ' . ($employee->name ?? ''))
            ->setSubject('Espelho de Ponto')
            ->setCreator($cfg['company_name'] ?? 'SupportPONTO')
            ->setCompany($cfg['company_name'] ?? 'SupportPONTO')
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Espelho de Ponto');

        $row = 1;
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, $company);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
        ]);
        $row += 2;

        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, 'ESPELHO DE PONTO — ' . strtoupper($employee->name ?? ''));
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_LIGHT]],
        ]);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'Período:');
        $sheet->setCellValue('B' . $row, $this->periodLabel($filters));
        $sheet->setCellValue('D' . $row, 'Tipo:');
        $sheet->setCellValue('E' . $row, ! empty($filters['type']) ? $this->typeLabel($filters['type']) : 'Todos');
        $row++;
        $sheet->setCellValue('A' . $row, 'Método:');
        $sheet->setCellValue('B' . $row, ! empty($filters['method']) ? $this->methodLabel($filters['method']) : 'Todos');
        $sheet->setCellValue('D' . $row, 'Gerado em:');
        $sheet->setCellValue('E' . $row, date('d/m/Y H:i'));
        foreach (['A' . ($row - 1), 'D' . ($row - 1), 'A' . $row, 'D' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(9);
        }
        $row += 2;

        $headers = ['Data', 'Hora', 'Tipo', 'Método', 'Status', 'NSR'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];
        $widths = [13, 10, 20, 16, 12, 14];
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
        foreach ($punches as $punch) {
            $punchTime = (string) ($punch->punch_time ?? '');
            $isValid = filter_var($punch->is_valid ?? true, FILTER_VALIDATE_BOOLEAN);

            $sheet->fromArray([
                $punchTime ? date('d/m/Y', strtotime($punchTime)) : '—',
                $punchTime ? date('H:i', strtotime($punchTime)) : '—',
                $this->typeLabel($punch->punch_type ?? null),
                $this->methodLabel($punch->method ?? null),
                $isValid ? 'Válido' : 'Revisar',
                (string) ($punch->nsr ?? '—'),
            ], null, "A{$dataRow}");

            $bg = $dataRow % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            $sheet->getStyle("A{$dataRow}:F{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);
            if (! $isValid) {
                $sheet->getStyle("E{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_YELLOW]],
                ]);
            }

            $dataRow++;
        }

        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:F" . ($dataRow - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }

        $filename = 'espelho_ponto_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ['content' => ob_get_clean(), 'filename' => $filename];
    }
}

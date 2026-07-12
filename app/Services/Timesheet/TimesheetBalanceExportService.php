<?php

namespace App\Services\Timesheet;

use App\Services\Pdf\PdfDocumentFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TimesheetBalanceExportService
{
    // ── Paleta ────────────────────────────────────────────────────────────────
    private const C_NAVY   = '#1B3A6B';
    private const C_BLUE   = '#2E86AB';
    private const C_LIGHT  = '#EBF3FA';
    private const C_STRIPE = '#F7FBFF';
    private const C_WHITE  = '#FFFFFF';
    private const C_BORDER = '#C8DCF0';
    private const C_TEXT   = '#1A2636';
    private const C_MUTED  = '#5A6A7E';
    private const C_GREEN  = '#1A6B3A';
    private const C_RED    = '#8B1A1A';
    private const C_YELLOW = '#7A5C00';

    // Excel hex (sem #)
    private const E_NAVY   = '1B3A6B';
    private const E_BLUE   = '2E86AB';
    private const E_LIGHT  = 'EBF3FA';
    private const E_STRIPE = 'F7FBFF';
    private const E_GREEN  = '1A6B3A';
    private const E_RED    = 'C0392B';
    private const E_YELLOW = 'F59E0B';
    private const E_WHITE  = 'FFFFFF';
    private const E_TEXT   = '1A2636';
    private const E_MUTED  = '5A6A7E';

    // ── Configurações da empresa (carregadas do banco) ────────────────────────

    private function companySettings(): array
    {
        try {
            $db   = \Config\Database::connect();
            $rows = $db->table('settings')
                ->whereIn('key', ['company_name','company_cnpj','company_logo','logo_path','company_phone','company_email','company_address','company_city','company_state'])
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
        if (!$rel) return null;
        $abs = FCPATH . $rel;
        return file_exists($abs) ? $abs : null;
    }

    // ── Helpers de formatação ─────────────────────────────────────────────────

    private function fmtDate(?string $d): string
    {
        if (empty($d)) return '—';
        $ts = strtotime((string)$d);
        return $ts ? date('d/m/Y', $ts) : (string)$d;
    }

    private function fmtH(float $h): string
    {
        return ($h >= 0 ? '+' : '') . number_format($h, 2) . 'h';
    }

    private function dayPt(string $date): string
    {
        $map = ['Mon'=>'Seg','Tue'=>'Ter','Wed'=>'Qua','Thu'=>'Qui','Fri'=>'Sex','Sat'=>'Sáb','Sun'=>'Dom'];
        return $map[date('D', strtotime($date))] ?? '—';
    }

    private function statusLabel(object $rec): string
    {
        if (!empty($rec->incomplete))  return 'Incompleto';
        if (!empty($rec->justified))   return 'Justificado';
        return 'OK';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════════

    public function buildPdf(object $employee, array $records, array $balance, array $statistics, int $days): array
    {
        $cfg      = $this->companySettings();
        $logo     = $this->resolvedLogoPath($cfg);
        $company  = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj     = $cfg['company_cnpj'] ?? '';

        $factory = new PdfDocumentFactory($company, $cnpj, $logo);
        $pdf     = $factory->create('Relatório de Saldo de Horas — ' . ($employee->name ?? ''), 'L');

        $html = $this->pdfHtml($employee, $records, $balance, $statistics, $days, $cfg, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'saldo_horas_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.pdf';
        return ['content' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    private function pdfHtml(object $emp, array $records, array $balance, array $statistics, int $days, array $cfg, PdfDocumentFactory $factory): string
    {
        $balVal  = (float)($balance['balance'] ?? 0);
        $balCls  = $balVal >= 0 ? self::C_GREEN : self::C_RED;
        $period  = 'Últimos ' . $days . ' dias — até ' . date('d/m/Y');

        $h  = '<style>';
        $h .= 'body{font-family:helvetica;font-size:8pt;color:' . self::C_TEXT . ';}';
        $h .= 'h2{font-size:9pt;font-weight:bold;color:' . self::C_NAVY . ';margin:6px 0 2px;background:' . self::C_LIGHT . ';padding:3px 6px;border-left:3px solid ' . self::C_BLUE . ';}';
        $h .= 'table{border-collapse:collapse;width:100%;margin-bottom:6px;}';
        $h .= 'th{background:' . self::C_NAVY . ';color:#fff;font-size:7pt;padding:3px 4px;text-align:center;font-weight:bold;}';
        $h .= 'td{font-size:7.5pt;padding:2.5px 4px;border-bottom:0.3px solid ' . self::C_BORDER . ';}';
        $h .= '.lbl{color:' . self::C_MUTED . ';font-size:7pt;}';
        $h .= '.val{font-weight:bold;color:' . self::C_TEXT . ';}';
        $h .= '.ok{color:' . self::C_GREEN . ';font-weight:bold;}';
        $h .= '.bad{color:' . self::C_RED . ';font-weight:bold;}';
        $h .= '.warn{color:' . self::C_YELLOW . ';font-weight:bold;}';
        $h .= '.stripe{background:' . self::C_STRIPE . ';}';
        $h .= '.inc{background:#FFFBEB;}';
        $h .= '</style>';

        // ── Cabeçalho do relatório (bloco HTML extra abaixo do header TCPDF)
        $h .= $factory->reportHeader('Relatório de Saldo de Horas', $period);

        // ── Ficha do colaborador ────────────────────────────────────────────
        $h .= '<h2>Dados do Colaborador</h2>';
        $h .= '<table border="0" cellpadding="3" cellspacing="0" style="width:100%;margin-bottom:8px;border:0.5px solid ' . self::C_BORDER . ';">';

        $fields = [
            ['Nome completo',  esc($emp->name ?? '—')],
            ['CPF',            esc($this->maskCpf($emp->cpf ?? ''))],
            ['E-mail',         esc($emp->email ?? '—')],
            ['Telefone',       esc($emp->phone ?? $emp->telefone ?? '—')],
            ['Departamento',   esc($emp->department ?? '—')],
            ['Cargo',          esc($emp->position ?? $emp->cargo ?? '—')],
            ['Admissão',       $this->fmtDate($emp->admission_date ?? null)],
            ['Código único',   esc($emp->unique_code ?? '—')],
            ['Jornada diária', esc($emp->expected_hours_daily ?? $emp->daily_hours ?? '—') . 'h'],
            ['Horário',        esc(($emp->work_schedule_start ?? $emp->work_start_time ?? '—') . ' – ' . ($emp->work_schedule_end ?? $emp->work_end_time ?? '—'))],
        ];

        // 2 colunas por linha
        $chunks = array_chunk($fields, 2);
        foreach ($chunks as $i => $pair) {
            $bg = $i % 2 === 0 ? self::C_WHITE : self::C_STRIPE;
            $h .= '<tr style="background:' . $bg . ';">';
            foreach ($pair as [$lbl, $val]) {
                $h .= '<td width="15%" style="color:' . self::C_MUTED . ';font-size:7pt;">' . $lbl . '</td>';
                $h .= '<td width="35%" style="font-weight:bold;font-size:8pt;">' . $val . '</td>';
            }
            if (count($pair) < 2) {
                $h .= '<td width="15%"></td><td width="35%"></td>';
            }
            $h .= '</tr>';
        }
        $h .= '</table>';

        // ── KPIs ────────────────────────────────────────────────────────────
        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Horas Extras',     '+' . number_format($balance['extra']  ?? 0, 2) . 'h', self::C_GREEN],
            ['Horas Devidas',    '-' . number_format($balance['owed']   ?? 0, 2) . 'h', self::C_RED],
            ['Saldo Total',       $this->fmtH($balVal),                                  $balCls],
            ['Dias Trabalhados', (string)($statistics['total_days']      ?? 0),           self::C_NAVY],
            ['Dias Incompletos', (string)($statistics['incomplete_days'] ?? 0),           ($statistics['incomplete_days'] ?? 0) > 0 ? self::C_YELLOW : self::C_NAVY],
            ['Média Diária',     number_format($statistics['avg_worked'] ?? 0, 2) . 'h', self::C_BLUE],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        // ── Tabela de registros ──────────────────────────────────────────────
        $h .= '<h2>Registros Detalhados</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $cols = [
            ['Data',       '9%'],  ['Dia',    '5%'],  ['Entrada', '7%'],  ['Saída',   '7%'],
            ['Intervalo',  '8%'],  ['Trab.',  '8%'],  ['Esperado','8%'],  ['Extras',  '8%'],
            ['Devidas',    '8%'],  ['Status', '9%'],
        ];
        $h .= '<thead><tr>';
        foreach ($cols as [$lbl, $w]) {
            $h .= '<th width="' . $w . '">' . $lbl . '</th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($records as $i => $rec) {
            $date    = (string)($rec->date ?? '');
            $extra   = (float)($rec->extra ?? 0);
            $owed    = (float)($rec->owed  ?? 0);
            $inc     = !empty($rec->incomplete);
            $just    = !empty($rec->justified);
            $rowCls  = $inc ? 'inc' : ($i % 2 === 0 ? '' : 'stripe');
            $status  = $inc
                ? '<span class="warn">Incompleto</span>'
                : ($just ? '<span class="ok">Justificado</span>' : '<span class="ok">OK</span>');

            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td align="center">' . ($date ? date('d/m/Y', strtotime($date)) : '—') . '</td>';
            $h .= '<td align="center">' . ($date ? $this->dayPt($date) : '—') . '</td>';
            $h .= '<td align="center">' . esc($rec->first_punch ?? '—') . '</td>';
            $h .= '<td align="center">' . esc($rec->last_punch  ?? '—') . '</td>';
            $h .= '<td align="center">' . number_format((float)($rec->total_interval ?? 0), 2) . 'h</td>';
            $h .= '<td align="center" style="font-weight:bold;">' . number_format((float)($rec->total_worked ?? 0), 2) . 'h</td>';
            $h .= '<td align="center">' . number_format((float)($rec->expected ?? 0), 2) . 'h</td>';
            $h .= '<td align="center">' . ($extra > 0 ? '<span class="ok">+' . number_format($extra, 2) . 'h</span>' : '—') . '</td>';
            $h .= '<td align="center">' . ($owed  > 0 ? '<span class="bad">-' . number_format($owed, 2)  . 'h</span>' : '—') . '</td>';
            $h .= '<td align="center">' . $status . '</td>';
            $h .= '</tr>';
        }

        $h .= '</tbody></table>';

        // ── Rodapé de assinatura ─────────────────────────────────────────────
        $h .= '<br><br>';
        $h .= '<table border="0" cellpadding="4" cellspacing="0" style="width:100%;margin-top:10px;">';
        $h .= '<tr>';
        $h .= '<td width="40%" align="center" style="border-top:0.5px solid #aaa;font-size:7pt;color:' . self::C_MUTED . ';">Colaborador: ' . esc($emp->name ?? '') . '</td>';
        $h .= '<td width="20%"></td>';
        $h .= '<td width="40%" align="center" style="border-top:0.5px solid #aaa;font-size:7pt;color:' . self::C_MUTED . ';">Responsável / Gestor</td>';
        $h .= '</tr>';
        $h .= '</table>';

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

    // ══════════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════════

    public function buildExcel(object $employee, array $records, array $balance, array $statistics, int $days): array
    {
        $cfg  = $this->companySettings();
        $logo = $this->resolvedLogoPath($cfg);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Saldo de Horas — ' . ($employee->name ?? ''))
            ->setSubject('Relatório de Saldo de Horas')
            ->setCreator($cfg['company_name'] ?? 'SupportPONTO')
            ->setCompany($cfg['company_name'] ?? 'SupportPONTO')
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $this->excelSummarySheet($spreadsheet->getActiveSheet(), $employee, $balance, $statistics, $days, $cfg, $logo);

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Registros Detalhados');
        $this->excelDetailSheet($detail, $records);

        $filename = 'saldo_horas_' . date('Ymd') . '_' . preg_replace('/[^a-z0-9]/i', '_', $employee->name ?? 'colaborador') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ['content' => ob_get_clean(), 'filename' => $filename];
    }

    private function excelSummarySheet($sheet, object $emp, array $balance, array $statistics, int $days, array $cfg, ?string $logo): void
    {
        $sheet->setTitle('Resumo');

        $row = 1;

        // ── Logo ──────────────────────────────────────────────────────────────
        if ($logo) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath($logo);
            $drawing->setCoordinates('A' . $row);
            $drawing->setHeight(50);
            $drawing->setWorksheet($sheet);
            $row = 5;
        }

        // ── Empresa ───────────────────────────────────────────────────────────
        $company = $cfg['company_name'] ?? 'SupportPONTO';
        $cnpj    = $cfg['company_cnpj']  ?? '';

        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, $company);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $row++;

        if ($cnpj) {
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $sheet->setCellValue('A' . $row, 'CNPJ: ' . $cnpj);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font'      => ['size' => 9, 'color' => ['argb' => 'FF' . self::E_MUTED]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $row++;
        }

        // Linha separadora
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
        ]);
        $row++;
        $row++; // espaço

        // ── Título do relatório ───────────────────────────────────────────────
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, 'RELATÓRIO DE SALDO DE HORAS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_LIGHT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
        $row++;

        // ── Ficha do colaborador ──────────────────────────────────────────────
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, 'DADOS DO COLABORADOR');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16);
        $row++;

        $empFields = [
            ['Nome completo',   $emp->name ?? '—'],
            ['CPF',             $this->maskCpf($emp->cpf ?? '')],
            ['E-mail',          $emp->email ?? '—'],
            ['Telefone',        $emp->phone ?? $emp->telefone ?? '—'],
            ['Departamento',    $emp->department ?? '—'],
            ['Cargo',           $emp->position ?? $emp->cargo ?? '—'],
            ['Data de admissão',$this->fmtDate($emp->admission_date ?? null)],
            ['Código único',    $emp->unique_code ?? '—'],
            ['Jornada diária',  ($emp->expected_hours_daily ?? $emp->daily_hours ?? '—') . 'h'],
            ['Horário',         ($emp->work_schedule_start ?? $emp->work_start_time ?? '—') . ' – ' . ($emp->work_schedule_end ?? $emp->work_end_time ?? '—')],
        ];

        foreach ($empFields as $i => [$lbl, $val]) {
            $bg = $i % 2 === 0 ? 'FFFFFFFF' : 'FF' . self::E_LIGHT;
            $sheet->setCellValue('A' . $row, $lbl);
            $sheet->mergeCells('B' . $row . ':D' . $row);
            $sheet->setCellValue('B' . $row, (string)$val);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['size' => 8, 'color' => ['argb' => 'FF' . self::E_MUTED]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            ]);
            $sheet->getStyle('B' . $row . ':D' . $row)->applyFromArray([
                'font' => ['size' => 8, 'bold' => true, 'color' => ['argb' => 'FF' . self::E_TEXT]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(14);
            $row++;
        }
        $row++;

        // ── Período / gerado em ────────────────────────────────────────────────
        $sheet->setCellValue('A' . $row, 'Período:');
        $sheet->setCellValue('B' . $row, 'Últimos ' . $days . ' dias (até ' . date('d/m/Y') . ')');
        $sheet->setCellValue('F' . $row, 'Gerado em:');
        $sheet->setCellValue('G' . $row, date('d/m/Y H:i'));
        foreach (['A' . $row, 'F' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(8);
        }
        $row++;
        $row++;

        // ── KPIs ──────────────────────────────────────────────────────────────
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, 'RESUMO DO PERÍODO');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16);
        $row++;

        $balVal  = (float)($balance['balance'] ?? 0);
        $kpis = [
            ['Horas Extras',    '+' . number_format($balance['extra']  ?? 0, 2) . 'h', self::E_GREEN],
            ['Horas Devidas',   '-' . number_format($balance['owed']   ?? 0, 2) . 'h', self::E_RED],
            ['Saldo Total',      ($balVal >= 0 ? '+' : '') . number_format($balVal, 2) . 'h', $balVal >= 0 ? self::E_GREEN : self::E_RED],
            ['Dias Trabalhados', (string)($statistics['total_days']      ?? 0), self::E_NAVY],
            ['Dias Incompletos', (string)($statistics['incomplete_days'] ?? 0), ($statistics['incomplete_days'] ?? 0) > 0 ? self::E_YELLOW : self::E_NAVY],
            ['Média Diária',     number_format($statistics['avg_worked'] ?? 0, 2) . 'h', self::E_BLUE],
        ];

        $kpiCols = ['A','B','C','D','E','F'];
        $headerRow = $row;
        $valRow    = $row + 1;

        foreach ($kpiCols as $i => $col) {
            $sheet->setCellValue("{$col}{$headerRow}", $kpis[$i][0]);
            $sheet->setCellValue("{$col}{$valRow}",    $kpis[$i][1]);
            $sheet->getStyle("{$col}{$headerRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 7.5, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $kpis[$i][2]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
            $sheet->getStyle("{$col}{$valRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . $kpis[$i][2]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . $kpis[$i][2]]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(16);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(14);
        $sheet->getRowDimension($valRow)->setRowHeight(28);
        $row = $valRow + 2;

        // ── Coluna A width ───────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
    }

    private function excelDetailSheet($sheet, array $records): void
    {
        $headers = ['Data','Dia','Entrada','Saída','Intervalo (h)','Trabalhado (h)','Esperado (h)','Extras (h)','Devidas (h)','Status'];
        $cols    = ['A','B','C','D','E','F','G','H','I','J'];
        $widths  = [13, 10, 10, 10, 14, 16, 14, 12, 12, 14];

        // Header
        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}1", $headers[$i]);
            $sheet->getColumnDimension($col)->setWidth($widths[$i]);
        }
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8.5, 'color' => ['argb' => 'FF' . self::E_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $sheet->setAutoFilter('A1:J1');
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($records as $rec) {
            $date  = (string)($rec->date ?? '');
            $extra = (float)($rec->extra ?? 0);
            $owed  = (float)($rec->owed  ?? 0);
            $inc   = !empty($rec->incomplete);
            $just  = !empty($rec->justified);

            $sheet->fromArray([
                $date ? date('d/m/Y', strtotime($date)) : '—',
                $date ? $this->dayPt($date) : '—',
                $rec->first_punch ?? '—',
                $rec->last_punch  ?? '—',
                number_format((float)($rec->total_interval ?? 0), 2),
                number_format((float)($rec->total_worked   ?? 0), 2),
                number_format((float)($rec->expected       ?? 0), 2),
                $extra > 0 ? number_format($extra, 2) : '',
                $owed  > 0 ? number_format($owed,  2) : '',
                $this->statusLabel($rec),
            ], null, "A{$row}");

            // Zebra
            $bg = $row % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            if ($inc) $bg = 'FFFFFBEB';
            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);

            // Cor células de extras/devidas
            if ($extra > 0) {
                $sheet->getStyle("H{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_GREEN]],
                ]);
            }
            if ($owed > 0) {
                $sheet->getStyle("I{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_RED]],
                ]);
            }

            $sheet->getRowDimension($row)->setRowHeight(13);
            $row++;
        }

        // Bordas externas na tabela inteira
        if ($row > 2) {
            $sheet->getStyle('A1:J' . ($row - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }
    }
}

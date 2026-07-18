<?php

namespace App\Services\Audit;

use App\Models\AuditModel;
use App\Models\SettingModel;
use App\Services\Pdf\PdfDocumentFactory;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuditExportService
{
    private const E_NAVY   = '1B3A6B';
    private const E_LIGHT  = 'EBF3FA';
    private const E_WHITE  = 'FFFFFF';
    private const E_RED    = 'C0392B';
    private const E_YELLOW = 'F59E0B';

    private const C_NAVY   = '#1B3A6B';
    private const C_BLUE   = '#2E86AB';
    private const C_LIGHT  = '#EBF3FA';
    private const C_STRIPE = '#F7FBFF';
    private const C_BORDER = '#C8DCF0';
    private const C_TEXT   = '#1A2636';
    private const C_RED    = '#8B1A1A';
    private const C_YELLOW = '#7A5C00';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly SettingModel $settingsModel = new SettingModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self(db_connect(), new AuditModel(), new SettingModel());
    }

    /**
     * @return list<object>
     */
    private function fetchLogs(string $dateFrom, string $dateTo): array
    {
        $auditTable = $this->auditModel->getTable();

        return $this->db->table($auditTable)
            ->select($auditTable . '.*, employees.name AS user_name')
            ->join('employees', 'employees.id = ' . $auditTable . '.user_id', 'left')
            ->where($auditTable . '.created_at >=', $dateFrom . ' 00:00:00')
            ->where($auditTable . '.created_at <=', $dateTo . ' 23:59:59')
            ->orderBy($auditTable . '.created_at', 'DESC')
            ->get()
            ->getResult();
    }

    private function companySettings(): array
    {
        return [
            'name' => (string) $this->settingsModel->get('company_name', 'SupportPONTO'),
            'cnpj' => (string) $this->settingsModel->get('company_cnpj', ''),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // CSV
    // ══════════════════════════════════════════════════════════════════════

    public function csvExport(string $dateFrom, string $dateTo): array
    {
        $logs = $this->fetchLogs($dateFrom, $dateTo);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['ID', 'Data/Hora', 'Usuário', 'Ação', 'Entidade', 'Registro ID', 'Descrição', 'Nível', 'IP']);

        foreach ($logs as $log) {
            $entityType = $log->entity_type ?? $log->table_name ?? '';
            $entityId = $log->entity_id ?? $log->record_id ?? '';

            fputcsv($stream, [
                $log->id,
                format_datetime_br($log->created_at),
                $log->user_name ?? 'Sistema',
                $log->action,
                $entityType,
                $entityId,
                $log->description,
                $log->level,
                $log->ip_address ?? '',
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return [
            'filename' => "audit_log_{$dateFrom}_to_{$dateTo}.csv",
            'content' => $content,
            'count' => count($logs),
        ];
    }

    public function logCsvExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AUDIT_EXPORTED',
            'audit_logs',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records, 'format' => 'csv'],
            "Auditoria exportada em CSV: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════════════

    public function pdfExport(string $dateFrom, string $dateTo): array
    {
        $logs = $this->fetchLogs($dateFrom, $dateTo);
        $company = $this->companySettings();

        $factory = new PdfDocumentFactory($company['name'], $company['cnpj']);
        $pdf = $factory->create('Log de Auditoria', 'L');

        $html = $this->pdfHtml($logs, $dateFrom, $dateTo, $factory);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = "audit_log_{$dateFrom}_to_{$dateTo}.pdf";

        return [
            'filename' => $filename,
            'content' => $pdf->Output($filename, 'S'),
            'count' => count($logs),
        ];
    }

    private function pdfHtml(array $logs, string $dateFrom, string $dateTo, PdfDocumentFactory $factory): string
    {
        $h = '<style>';
        $h .= 'body{font-family:helvetica;font-size:8pt;color:' . self::C_TEXT . ';}';
        $h .= 'h2{font-size:9pt;font-weight:bold;color:' . self::C_NAVY . ';margin:6px 0 2px;background:' . self::C_LIGHT . ';padding:3px 6px;border-left:3px solid ' . self::C_BLUE . ';}';
        $h .= 'table{border-collapse:collapse;width:100%;margin-bottom:6px;}';
        $h .= 'th{background:' . self::C_NAVY . ';color:#fff;font-size:7pt;padding:3px 4px;text-align:center;font-weight:bold;}';
        $h .= 'td{font-size:7pt;padding:2.5px 4px;border-bottom:0.3px solid ' . self::C_BORDER . ';}';
        $h .= '.stripe{background:' . self::C_STRIPE . ';}';
        $h .= '.lvl-critical{color:' . self::C_RED . ';font-weight:bold;}';
        $h .= '.lvl-error{color:' . self::C_RED . ';}';
        $h .= '.lvl-warning{color:' . self::C_YELLOW . ';}';
        $h .= '</style>';

        $period = date('d/m/Y', strtotime($dateFrom)) . ' a ' . date('d/m/Y', strtotime($dateTo));
        $h .= $factory->reportHeader('Log de Auditoria', $period);

        $counts = ['critical' => 0, 'error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($logs as $log) {
            $lvl = strtolower((string) ($log->level ?? 'info'));
            if (isset($counts[$lvl])) {
                $counts[$lvl]++;
            }
        }

        $h .= '<h2>Resumo do Período</h2>';
        $kpis = [
            ['Total de Eventos', (string) count($logs), self::C_NAVY],
            ['Críticos', (string) $counts['critical'], self::C_RED],
            ['Erros', (string) $counts['error'], self::C_RED],
            ['Atenção', (string) $counts['warning'], self::C_YELLOW],
        ];
        $h .= '<table border="0" cellpadding="0" cellspacing="2" style="width:100%;margin-bottom:8px;"><tr>';
        foreach ($kpis as [$lbl, $val, $color]) {
            $h .= '<td align="center" style="background:' . $color . ';color:#fff;padding:5px 2px;border-radius:2px;">'
                . '<span style="font-size:13pt;font-weight:bold;">' . $val . '</span><br>'
                . '<span style="font-size:6.5pt;">' . strtoupper($lbl) . '</span>'
                . '</td><td width="1%"></td>';
        }
        $h .= '</tr></table>';

        $h .= '<h2>Eventos</h2>';
        $h .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $h .= '<thead><tr>';
        $h .= '<th width="12%">Data/Hora</th><th width="12%">Usuário</th><th width="14%">Ação</th><th width="12%">Entidade</th><th width="32%">Descrição</th><th width="8%">Nível</th><th width="10%">IP</th>';
        $h .= '</tr></thead><tbody>';

        foreach ($logs as $i => $log) {
            $entityType = $log->entity_type ?? $log->table_name ?? '';
            $entityId = $log->entity_id ?? $log->record_id ?? null;
            $entity = $entityType !== '' ? $entityType . ($entityId ? " #{$entityId}" : '') : '—';
            $lvl = strtolower((string) ($log->level ?? 'info'));
            $rowCls = $i % 2 === 0 ? '' : 'stripe';

            $h .= '<tr class="' . $rowCls . '">';
            $h .= '<td>' . esc(format_datetime_br((string) $log->created_at, false)) . '</td>';
            $h .= '<td>' . esc((string) ($log->user_name ?? 'Sistema')) . '</td>';
            $h .= '<td>' . esc((string) $log->action) . '</td>';
            $h .= '<td>' . esc($entity) . '</td>';
            $h .= '<td>' . esc((string) ($log->description ?? '—')) . '</td>';
            $h .= '<td align="center" class="lvl-' . esc($lvl) . '">' . esc(strtoupper($lvl)) . '</td>';
            $h .= '<td>' . esc((string) ($log->ip_address ?? '—')) . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';

        return $h;
    }

    public function logPdfExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AUDIT_EXPORTED',
            'audit_logs',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records, 'format' => 'pdf'],
            "Auditoria exportada em PDF: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // EXCEL
    // ══════════════════════════════════════════════════════════════════════

    public function excelExport(string $dateFrom, string $dateTo): array
    {
        $logs = $this->fetchLogs($dateFrom, $dateTo);
        $company = $this->companySettings();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Log de Auditoria')
            ->setSubject('Auditoria')
            ->setCreator($company['name'])
            ->setCompany($company['name'])
            ->setDescription('Gerado em ' . date('d/m/Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoria');

        $row = 1;
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, $company['name']);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::E_NAVY]],
        ]);
        $row += 2;

        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, 'LOG DE AUDITORIA');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . self::E_NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::E_LIGHT]],
        ]);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'Período:');
        $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($dateFrom)) . ' a ' . date('d/m/Y', strtotime($dateTo)));
        $sheet->setCellValue('D' . $row, 'Gerado em:');
        $sheet->setCellValue('E' . $row, date('d/m/Y H:i'));
        foreach (['A' . $row, 'D' . $row] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true)->setSize(9);
        }
        $row += 2;

        $headers = ['ID', 'Data/Hora', 'Usuário', 'Ação', 'Entidade', 'Registro ID', 'Descrição', 'Nível', 'IP'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $widths = [8, 16, 22, 22, 16, 12, 45, 10, 14];
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
        foreach ($logs as $log) {
            $entityType = $log->entity_type ?? $log->table_name ?? '';
            $entityId = $log->entity_id ?? $log->record_id ?? '';
            $level = strtoupper((string) ($log->level ?? 'info'));

            $sheet->fromArray([
                $log->id,
                format_datetime_br($log->created_at),
                $log->user_name ?? 'Sistema',
                $log->action,
                $entityType,
                $entityId,
                $log->description,
                $level,
                $log->ip_address ?? '',
            ], null, "A{$dataRow}");

            $bg = $dataRow % 2 === 0 ? 'FF' . self::E_LIGHT : 'FFFFFFFF';
            $sheet->getStyle("A{$dataRow}:I{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FF' . self::E_LIGHT]]],
            ]);
            if (in_array($level, ['CRITICAL', 'ERROR'], true)) {
                $sheet->getStyle("H{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_RED]],
                ]);
            } elseif ($level === 'WARNING') {
                $sheet->getStyle("H{$dataRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::E_YELLOW]],
                ]);
            }

            $dataRow++;
        }

        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:I" . ($dataRow - 1))->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::E_NAVY]]],
            ]);
        }

        $filename = "audit_log_{$dateFrom}_to_{$dateTo}.xlsx";
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return [
            'filename' => $filename,
            'content' => $content,
            'count' => count($logs),
        ];
    }

    public function logExcelExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AUDIT_EXPORTED',
            'audit_logs',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records, 'format' => 'excel'],
            "Auditoria exportada em Excel: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // AFD
    // ══════════════════════════════════════════════════════════════════════

    public function afdExport(string $dateFrom, string $dateTo): array
    {
        $punches = $this->db->table('time_punches tp')
            ->select('tp.*, e.pis, e.name AS employee_name, e.cpf')
            ->join('employees e', 'e.id = tp.employee_id', 'left')
            ->where('tp.punch_time >=', $dateFrom . ' 00:00:00')
            ->where('tp.punch_time <=', $dateTo . ' 23:59:59')
            ->orderBy('tp.nsr', 'ASC')
            ->get()
            ->getResult();

        $companyName = $this->settingsModel->get('company_name', 'Empresa');
        $companyCNPJ = $this->settingsModel->get('company_cnpj', '00.000.000/0001-00');
        $content = $this->buildAfdContent($punches, $companyName, $companyCNPJ, $dateFrom, $dateTo);

        $filename = 'AFD_' . str_replace(['/', '-', '.'], '', $companyCNPJ)
            . '_' . date('Ymd', strtotime($dateFrom))
            . '_' . date('Ymd', strtotime($dateTo))
            . '.txt';

        return [
            'filename' => $filename,
            'content' => $content,
            'records' => count($punches),
        ];
    }

    public function logAfdExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AFD_EXPORTED',
            'time_punches',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records],
            "AFD exportado: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    private function buildAfdContent(array $punches, string $companyName, string $companyCNPJ, string $dateFrom, string $dateTo): string
    {
        $lines = [];
        $cnpjNumeric = preg_replace('/\D/', '', $companyCNPJ);

        $header = '1';
        $header .= '2';
        $header .= str_pad((string) $cnpjNumeric, 14, '0', STR_PAD_LEFT);
        $header .= str_pad('', 12, '0');
        $header .= str_pad(substr($companyName, 0, 150), 150);
        $header .= '000000000000000';
        $header .= date('dmY', strtotime($dateFrom));
        $header .= date('dmY', strtotime($dateTo));
        $header .= date('dmYHis');
        $lines[] = $header;

        $company = '2';
        $company .= '1';
        $company .= str_pad((string) $cnpjNumeric, 14, '0', STR_PAD_LEFT);
        $company .= str_pad('', 12, '0');
        $company .= str_pad(substr($companyName, 0, 150), 150);
        $lines[] = $company;

        foreach ($punches as $punch) {
            $pisNumeric = preg_replace('/\D/', '', $punch->pis ?? '');
            $punchTime = new \DateTime($punch->punch_time);

            $record = '3';
            $record .= str_pad((string) ($punch->nsr ?? 0), 9, '0', STR_PAD_LEFT);
            $record .= str_pad((string) $pisNumeric, 12, '0', STR_PAD_LEFT);
            $record .= $punchTime->format('dmY');
            $record .= $punchTime->format('Hi');
            $record .= $this->punchTypeCode((string) $punch->punch_type);
            $lines[] = $record;
        }

        $trailer = '9' . str_pad((string) count($lines), 9, '0', STR_PAD_LEFT);
        $lines[] = $trailer;

        return implode("\r\n", $lines);
    }

    private function punchTypeCode(string $punchType): string
    {
        $codes = [
            'entrada' => 'E',
            'saida' => 'S',
            'intervalo_inicio' => 'I',
            'intervalo_fim' => 'F',
        ];

        return $codes[$punchType] ?? 'O';
    }
}

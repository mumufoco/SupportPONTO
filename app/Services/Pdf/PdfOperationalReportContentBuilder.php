<?php

namespace App\Services\Pdf;

class PdfOperationalReportContentBuilder
{
    // Paleta corporativa
    private const C_NAVY    = '#1B3A6B';
    private const C_BLUE    = '#2E86AB';
    private const C_LIGHT   = '#EBF3FA';
    private const C_STRIPE  = '#F7FBFF';
    private const C_WHITE   = '#FFFFFF';
    private const C_BORDER  = '#C8DCF0';
    private const C_TEXT    = '#1A2636';
    private const C_MUTED   = '#5A6A7E';
    private const C_GREEN   = '#1A6B3A';
    private const C_RED     = '#8B1A1A';
    private const C_YELLOW  = '#7A5C00';

    public function __construct(
        private readonly PdfFilterHtmlBuilder $filterBuilder,
        private readonly ?PdfDocumentFactory  $docFactory = null,
    ) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function fmtH(float $h): string
    {
        $sign = $h < 0 ? '-' : '';
        $abs  = abs($h);
        $hh   = (int) $abs;
        $mm   = (int) round(($abs - $hh) * 60);
        return $sign . $hh . 'h' . str_pad($mm, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function fmtD(?string $d): string
    {
        if (empty($d)) return '-';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }

    private function kpi(string $label, string $value, string $color = self::C_NAVY): string
    {
        return '<td width="23%" align="center" style="background:' . $color . ';color:#FFFFFF;padding:7px 4px;border-radius:3px;border:1px solid ' . $color . ';">'
             . '<span style="font-size:13pt;font-weight:bold;">' . $value . '</span>'
             . '<br><span style="font-size:6.5pt;letter-spacing:0.5px;">' . strtoupper($label) . '</span>'
             . '</td>';
    }

    private function kpiRow(array $kpis): string
    {
        $cells = implode('<td width="2%"></td>', $kpis);
        return '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:8px;"><tr>' . $cells . '</tr></table>';
    }

    private function tableHeader(array $cols): string
    {
        $ths = '';
        foreach ($cols as [$label, $w]) {
            $ths .= '<th width="' . $w . '" style="background:' . self::C_NAVY . ';color:#FFFFFF;font-weight:bold;font-size:7pt;padding:5px 3px;text-align:center;">' . $label . '</th>';
        }
        return '<thead><tr>' . $ths . '</tr></thead>';
    }

    private function tableRow(array $cells, bool $odd): string
    {
        $bg  = $odd ? self::C_STRIPE : self::C_WHITE;
        $tds = '';
        foreach ($cells as [$val, $align, $color]) {
            $style = 'background:' . $bg . ';color:' . ($color ?? self::C_TEXT) . ';font-size:7pt;padding:4px 3px;border-bottom:1px solid ' . self::C_BORDER . ';text-align:' . $align . ';';
            $tds  .= '<td style="' . $style . '">' . $val . '</td>';
        }
        return '<tr>' . $tds . '</tr>';
    }

    private function wrap(string $html, string $title, string $prefix): array
    {
        return ['success' => true, 'title' => $title, 'html' => $html, 'filename' => $prefix . date('Y-m-d_His') . '.pdf'];
    }

    private function sectionTitle(string $t): string
    {
        return '<div style="background:' . self::C_LIGHT . ';border-left:4px solid ' . self::C_BLUE . ';padding:4px 8px;margin:8px 0 4px;font-weight:bold;font-size:8pt;color:' . self::C_NAVY . ';">' . $t . '</div>';
    }

    private function header(string $title, string $period, array $filters): string
    {
        $head = $this->docFactory ? $this->docFactory->reportHeader($title, $period) : '';
        $filt = $this->filterBuilder->build($filters);
        return $head . $filt;
    }

    private function footNote(int $count, string $extra = ''): string
    {
        $note = '<span style="font-size:6.5pt;color:' . self::C_MUTED . ';">Total: <strong>' . $count . '</strong> registro' . ($count !== 1 ? 's' : '');
        if ($extra) $note .= ' &nbsp;|&nbsp; ' . $extra;
        return $note . ' &nbsp;|&nbsp; Documento gerado automaticamente pelo Sistema de Ponto Eletrônico</span>';
    }

    // ── Relatório: Folha de Ponto ─────────────────────────────────────────────

    public function timesheet(array $data, array $filters): array
    {
        $period = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');

        $totalWorked   = array_sum(array_column($data, 'total_worked'));
        $totalExpected = array_sum(array_column($data, 'expected'));
        $totalExtra    = array_sum(array_column($data, 'extra'));
        $totalOwed     = array_sum(array_column($data, 'owed'));
        $balance       = $totalWorked - $totalExpected;

        $kpis = [
            $this->kpi('Registros',    (string) count($data),         self::C_NAVY),
            $this->kpi('Trabalhado',   $this->fmtH($totalWorked),     self::C_BLUE),
            $this->kpi('Saldo',        ($balance >= 0 ? '+' : '') . $this->fmtH($balance), $balance >= 0 ? self::C_GREEN : self::C_RED),
            $this->kpi('H. Extras',    $this->fmtH($totalExtra),      '#2A6B4A'),
        ];

        $html  = $this->header('Relatório de Folha de Ponto', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Registros Diários');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Data', '8%'], ['Colaborador', '18%'], ['Depto', '12%'],
            ['Entrada', '8%'], ['Saída', '8%'],
            ['Trabalhado', '10%'], ['Previsto', '10%'], ['Saldo', '10%'],
            ['Extras', '8%'], ['Débito', '8%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $tw  = (float)($record['total_worked'] ?? 0);
            $exp = (float)($record['expected']     ?? 0);
            $ext = (float)($record['extra']        ?? 0);
            $owd = (float)($record['owed']         ?? 0);
            $bal = isset($record['balance']) ? (float)$record['balance'] : ($tw - $exp);
            $balColor = $bal > 0 ? self::C_GREEN : ($bal < 0 ? self::C_RED : self::C_TEXT);

            $html .= $this->tableRow([
                [$this->fmtD($record['date'] ?? null),                            'center', null],
                [esc($record['employee_name'] ?? '-'),                            'left',   null],
                [esc($record['department']    ?? '-'),                            'left',   null],
                [esc($record['first_punch']   ?? '-'),                            'center', null],
                [esc($record['last_punch']    ?? '-'),                            'center', null],
                [$this->fmtH($tw),                                                'center', null],
                [$this->fmtH($exp),                                               'center', null],
                [($bal >= 0 ? '+' : '') . $this->fmtH($bal),                     'center', $balColor],
                [$ext > 0 ? $this->fmtH($ext) : '-',                             'center', $ext > 0 ? self::C_GREEN : null],
                [$owd > 0 ? $this->fmtH($owd) : '-',                             'center', $owd > 0 ? self::C_RED : null],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data), 'Total esperado: ' . $this->fmtH($totalExpected));

        return $this->wrap($html, 'Relatório de Folha de Ponto', 'relatorio_folha_ponto_');
    }

    // ── Relatório: Horas Extras ───────────────────────────────────────────────

    public function overtime(array $data, array $filters): array
    {
        $period    = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');
        $totalExt  = array_sum(array_column($data, 'extra'));
        $weekends  = count(array_filter($data, fn($r) => !empty($r['is_weekend'])));

        $kpis = [
            $this->kpi('Registros',      (string) count($data),         self::C_NAVY),
            $this->kpi('H. Extras',      $this->fmtH($totalExt),        self::C_GREEN),
            $this->kpi('H. Extras 50%',  $this->fmtH($totalExt * 1.5),  self::C_BLUE),
            $this->kpi('Fins de semana', (string) $weekends,             '#5A3A8B'),
        ];

        $html  = $this->header('Relatório de Horas Extras', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Detalhamento de Horas Extras');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Data', '9%'], ['Colaborador', '20%'], ['Depto', '14%'],
            ['Trabalhado', '11%'], ['Previsto', '11%'],
            ['Extras', '11%'], ['Extras 50%', '11%'], ['Dia', '13%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $ext = (float)($record['extra'] ?? 0);
            $tw  = (float)($record['total_worked'] ?? 0);
            $exp = (float)($record['expected']     ?? 0);
            $wk  = !empty($record['is_weekend']);

            $html .= $this->tableRow([
                [$this->fmtD($record['date'] ?? null),                            'center', null],
                [esc($record['employee_name'] ?? '-'),                            'left',   null],
                [esc($record['department']    ?? '-'),                            'left',   null],
                [$this->fmtH($tw),                                                'center', null],
                [$this->fmtH($exp),                                               'center', null],
                ['<strong>' . $this->fmtH($ext) . '</strong>',                   'center', self::C_GREEN],
                [$this->fmtH($ext * 1.5),                                         'center', self::C_BLUE],
                [$wk ? 'Fim de semana' : 'Dia útil',                              'center', $wk ? self::C_RED : null],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data), 'Total extras: ' . $this->fmtH($totalExt));

        return $this->wrap($html, 'Relatório de Horas Extras', 'relatorio_horas_extras_');
    }

    // ── Relatório: Faltas e Atrasos ───────────────────────────────────────────

    public function absence(array $data, array $filters): array
    {
        $period  = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');
        $faltas  = count(array_filter($data, fn($r) => ($r['type'] ?? '') === 'falta'));
        $atrasos = count(array_filter($data, fn($r) => ($r['type'] ?? '') !== 'falta'));
        $justif  = count(array_filter($data, fn($r) => !empty($r['justified'])));

        $kpis = [
            $this->kpi('Total',       (string) count($data), self::C_NAVY),
            $this->kpi('Faltas',      (string) $faltas,      self::C_RED),
            $this->kpi('Atrasos',     (string) $atrasos,     self::C_YELLOW),
            $this->kpi('Justificados', (string) $justif,     self::C_GREEN),
        ];

        $html  = $this->header('Relatório de Faltas e Atrasos', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Registros de Faltas e Atrasos');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Data', '8%'], ['Colaborador', '20%'], ['Depto', '13%'],
            ['Tipo', '8%'], ['Horário', '8%'], ['Esperado', '8%'],
            ['Atraso (min)', '10%'], ['Status', '10%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $type    = strtolower($record['type'] ?? '');
            $typeCol = $type === 'falta' ? self::C_RED : self::C_YELLOW;
            $just    = !empty($record['justified']);

            $html .= $this->tableRow([
                [$this->fmtD($record['date'] ?? null),                                     'center', null],
                [esc($record['employee_name'] ?? '-'),                                     'left',   null],
                [esc($record['department']    ?? '-'),                                     'left',   null],
                ['<strong>' . ucfirst($type) . '</strong>',                                'center', $typeCol],
                [esc($record['punch_time']    ?? '-'),                                     'center', null],
                [esc($record['expected_time'] ?? '-'),                                     'center', null],
                [(int)($record['delay_minutes'] ?? 0) . ' min',                            'center', $typeCol],
                [$just ? '<span style="color:' . self::C_GREEN . ';">Justificado</span>'
                       : '<span style="color:' . self::C_YELLOW . ';">Pendente</span>',    'center', null],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data));

        return $this->wrap($html, 'Relatório de Faltas e Atrasos', 'relatorio_faltas_atrasos_');
    }

    // ── Relatório: Banco de Horas ─────────────────────────────────────────────

    public function bankHours(array $data, array $filters): array
    {
        $totExt  = array_sum(array_column($data, 'extra_hours_balance'));
        $totOwd  = array_sum(array_column($data, 'owed_hours_balance'));
        $totBal  = $totExt - $totOwd;
        $credors = count(array_filter($data, fn($r) => ((float)($r['extra_hours_balance'] ?? 0) - (float)($r['owed_hours_balance'] ?? 0)) > 0));

        $kpis = [
            $this->kpi('Colaboradores',   (string) count($data),         self::C_NAVY),
            $this->kpi('Total Extras',   $this->fmtH($totExt),          self::C_GREEN),
            $this->kpi('Total Débitos',  $this->fmtH($totOwd),          self::C_RED),
            $this->kpi('Saldo Geral',    ($totBal >= 0 ? '+' : '') . $this->fmtH($totBal), $totBal >= 0 ? self::C_GREEN : self::C_RED),
        ];

        $html  = $this->header('Relatório de Banco de Horas', 'Posição atual', $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Posição de Banco de Horas por Colaborador');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Colaborador', '25%'], ['Departamento', '20%'],
            ['H. Extras Acum.', '15%'], ['H. Débitos Acum.', '15%'],
            ['Saldo Total', '13%'], ['Situação', '12%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $ext  = (float)($record['extra_hours_balance'] ?? 0);
            $owd  = (float)($record['owed_hours_balance']  ?? 0);
            $bal  = $ext - $owd;
            $sit  = $bal > 0 ? 'Credor' : ($bal < 0 ? 'Devedor' : 'Neutro');
            $sitC = $bal > 0 ? self::C_GREEN : ($bal < 0 ? self::C_RED : self::C_MUTED);

            $html .= $this->tableRow([
                [esc($record['employee_name'] ?? '-'),                            'left',   null],
                [esc($record['department']    ?? '-'),                            'left',   null],
                ['+' . $this->fmtH($ext),                                         'center', self::C_GREEN],
                ['-' . $this->fmtH($owd),                                         'center', self::C_RED],
                ['<strong>' . ($bal >= 0 ? '+' : '') . $this->fmtH($bal) . '</strong>', 'center', $sitC],
                ['<strong>' . $sit . '</strong>',                                  'center', $sitC],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data), 'Credores: ' . $credors . ' | Devedores: ' . (count($data) - $credors));

        return $this->wrap($html, 'Relatório de Banco de Horas', 'relatorio_banco_horas_');
    }

    // ── Relatório: Consolidado Mensal ─────────────────────────────────────────

    public function consolidated(array $data, array $filters): array
    {
        $period   = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');
        $totWork  = array_sum(array_column($data, 'total_worked'));
        $totExp   = array_sum(array_column($data, 'total_expected'));
        $totLate  = array_sum(array_column($data, 'late_count'));
        $totAbs   = array_sum(array_column($data, 'absence_count'));

        $kpis = [
            $this->kpi('Colaboradores',   (string) count($data),         self::C_NAVY),
            $this->kpi('Total Trabalhado', $this->fmtH($totWork),       self::C_BLUE),
            $this->kpi('Total Atrasos',  (string) $totLate,             self::C_YELLOW),
            $this->kpi('Total Faltas',   (string) $totAbs,              self::C_RED),
        ];

        $html  = $this->header('Relatório Consolidado Mensal', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Consolidado por Colaborador');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Colaborador', '17%'], ['Depto', '11%'], ['Dias', '6%'],
            ['Trabalhado', '10%'], ['Previsto', '10%'],
            ['Extras', '9%'], ['Débitos', '9%'], ['Saldo', '9%'],
            ['Atrasos', '7%'], ['Faltas', '6%'], ['% Pres.', '6%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $tw    = (float)($record['total_worked']   ?? 0);
            $exp   = (float)($record['total_expected'] ?? 0);
            $ext   = (float)($record['extra']  ?? 0);
            $owd   = (float)($record['owed']   ?? 0);
            $bal   = $ext - $owd;
            $pres  = $exp > 0 ? round(($tw / $exp) * 100, 1) : 0;
            $balC  = $bal > 0 ? self::C_GREEN : ($bal < 0 ? self::C_RED : self::C_TEXT);
            $presC = $pres >= 95 ? self::C_GREEN : ($pres >= 80 ? self::C_YELLOW : self::C_RED);

            $html .= $this->tableRow([
                [esc($record['employee_name'] ?? '-'),                                   'left',   null],
                [esc($record['department']    ?? '-'),                                   'left',   null],
                [(int)($record['days_worked'] ?? 0),                                     'center', null],
                [$this->fmtH($tw),                                                       'center', null],
                [$this->fmtH($exp),                                                      'center', null],
                [$ext > 0 ? '+' . $this->fmtH($ext) : '-',                              'center', $ext > 0 ? self::C_GREEN : null],
                [$owd > 0 ? '-' . $this->fmtH($owd) : '-',                              'center', $owd > 0 ? self::C_RED : null],
                [($bal >= 0 ? '+' : '') . $this->fmtH($bal),                            'center', $balC],
                [(int)($record['late_count']    ?? 0),                                   'center', (int)($record['late_count']    ?? 0) > 0 ? self::C_YELLOW : null],
                [(int)($record['absence_count'] ?? 0),                                   'center', (int)($record['absence_count'] ?? 0) > 0 ? self::C_RED : null],
                [$pres . '%',                                                             'center', $presC],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data), 'Total previsto: ' . $this->fmtH($totExp));

        return $this->wrap($html, 'Relatório Consolidado Mensal', 'relatorio_consolidado_');
    }
}

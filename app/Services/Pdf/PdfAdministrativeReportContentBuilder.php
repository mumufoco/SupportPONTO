<?php

namespace App\Services\Pdf;

class PdfAdministrativeReportContentBuilder
{
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

    public function __construct(
        private readonly PdfFilterHtmlBuilder $filterBuilder,
        private readonly ?PdfDocumentFactory  $docFactory = null,
    ) {}

    private function fmtD(?string $d): string
    {
        if (empty($d)) return '-';
        $ts = strtotime((string)$d);
        return $ts ? date('d/m/Y', $ts) : (string)$d;
    }

    private function kpi(string $label, string $value, string $color = self::C_NAVY): string
    {
        return '<td width="23%" align="center" style="background:' . $color . ';color:#FFFFFF;padding:7px 4px;">'
             . '<span style="font-size:13pt;font-weight:bold;">' . $value . '</span>'
             . '<br><span style="font-size:6.5pt;">' . strtoupper($label) . '</span>'
             . '</td>';
    }

    private function kpiRow(array $kpis): string
    {
        return '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:8px;"><tr>'
             . implode('<td width="2%"></td>', $kpis)
             . '</tr></table>';
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
        return $head . $this->filterBuilder->build($filters);
    }

    private function footNote(int $count): string
    {
        return '<span style="font-size:6.5pt;color:' . self::C_MUTED . ';">Total: <strong>' . $count . '</strong> registro' . ($count !== 1 ? 's' : '') . ' &nbsp;|&nbsp; Documento gerado automaticamente pelo Sistema de Ponto Eletrônico</span>';
    }

    private function statusBadge(string $status): string
    {
        $map = [
            'aprovado'  => [self::C_GREEN,  'Aprovado'],
            'approved'  => [self::C_GREEN,  'Aprovado'],
            'rejeitado' => [self::C_RED,    'Rejeitado'],
            'rejected'  => [self::C_RED,    'Rejeitado'],
            'pendente'  => [self::C_YELLOW, 'Pendente'],
            'pending'   => [self::C_YELLOW, 'Pendente'],
        ];
        [$color, $label] = $map[strtolower($status)] ?? [self::C_MUTED, ucfirst($status)];
        return '<span style="color:' . $color . ';font-weight:bold;">' . $label . '</span>';
    }

    // ── Relatório: Justificativas ─────────────────────────────────────────────

    public function justifications(array $data, array $filters): array
    {
        $period   = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');
        $approved = count(array_filter($data, fn($r) => in_array(strtolower($r['status'] ?? ''), ['approved','aprovado'])));
        $rejected = count(array_filter($data, fn($r) => in_array(strtolower($r['status'] ?? ''), ['rejected','rejeitado'])));
        $pending  = count($data) - $approved - $rejected;

        $kpis = [
            $this->kpi('Total',       (string) count($data), self::C_NAVY),
            $this->kpi('Aprovadas',   (string) $approved,    self::C_GREEN),
            $this->kpi('Pendentes',   (string) $pending,     self::C_YELLOW),
            $this->kpi('Rejeitadas',  (string) $rejected,    self::C_RED),
        ];

        $html  = $this->header('Relatório de Justificativas', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Justificativas do Período');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Data',         '8%'],
            ['Colaborador',  '18%'],
            ['Tipo',         '11%'],
            ['Categoria',    '11%'],
            ['Motivo',       '30%'],
            ['Status',       '9%'],
            ['Anexos',       '7%'],
            ['Cadastrado',   '6%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $date   = $this->fmtD($record['justification_date'] ?? $record['date'] ?? null);
            $reason = esc(mb_strimwidth((string)($record['reason'] ?? '-'), 0, 70, '…'));
            $type   = ucwords(str_replace('-', ' ', (string)($record['justification_type'] ?? '-')));
            $cat    = ucwords(str_replace('-', ' ', (string)($record['category'] ?? '-')));
            $hasAtt = !empty($record['has_attachments']) ? '<span style="color:' . self::C_BLUE . ';">Sim</span>' : 'Não';
            $created = $this->fmtD($record['created_at'] ?? null);

            $html .= $this->tableRow([
                [$date,                                          'center', null],
                [esc($record['employee_name'] ?? '-'),           'left',   null],
                [$type,                                          'left',   null],
                [$cat,                                           'left',   null],
                [$reason,                                        'left',   self::C_MUTED],
                [$this->statusBadge($record['status'] ?? ''),   'center', null],
                [$hasAtt,                                        'center', null],
                [$created,                                       'center', self::C_MUTED],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data));

        return $this->wrap($html, 'Relatório de Justificativas', 'relatorio_justificativas_');
    }

    // ── Relatório: Advertências ───────────────────────────────────────────────

    public function warnings(array $data, array $filters): array
    {
        $period  = !empty($filters['start_date']) ? $filters['start_date'] . ' a ' . ($filters['end_date'] ?? '') : date('m/Y');
        $susp    = count(array_filter($data, fn($r) => strtolower($r['warning_type'] ?? '') === 'suspensao'));
        $escrita = count(array_filter($data, fn($r) => strtolower($r['warning_type'] ?? '') === 'escrita'));
        $verbal  = count(array_filter($data, fn($r) => !in_array(strtolower($r['warning_type'] ?? ''), ['suspensao','escrita'])));

        $kpis = [
            $this->kpi('Total',          (string) count($data), self::C_NAVY),
            $this->kpi('Suspensões',     (string) $susp,        self::C_RED),
            $this->kpi('Escritas',       (string) $escrita,     self::C_YELLOW),
            $this->kpi('Verbais/Outros', (string) $verbal,      self::C_BLUE),
        ];

        $html  = $this->header('Relatório de Advertências', 'Período: ' . $period, $filters);
        $html .= $this->kpiRow($kpis);
        $html .= $this->sectionTitle('Registros de Advertências');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= $this->tableHeader([
            ['Data',        '8%'],
            ['Colaborador', '20%'],
            ['Depto',       '12%'],
            ['Tipo',        '11%'],
            ['Motivo',      '33%'],
            ['Status',      '10%'],
            ['Abertura',    '6%'],
        ]);
        $html .= '<tbody>';
        $odd = true;
        foreach ($data as $record) {
            $wtype = strtolower($record['warning_type'] ?? '');
            $typeC = $wtype === 'suspensao' ? self::C_RED : ($wtype === 'escrita' ? self::C_YELLOW : self::C_BLUE);
            $reason = esc(mb_strimwidth((string)($record['reason'] ?? '-'), 0, 80, '…'));

            $html .= $this->tableRow([
                [$this->fmtD($record['date']          ?? null), 'center', null],
                [esc($record['employee_name']          ?? '-'),  'left',   null],
                [esc($record['department']             ?? '-'),  'left',   null],
                ['<strong>' . ucfirst($wtype) . '</strong>',     'center', $typeC],
                [$reason,                                         'left',   self::C_MUTED],
                [ucfirst($record['status']             ?? '-'),  'center', null],
                [$this->fmtD($record['created_at']    ?? null), 'center', self::C_MUTED],
            ], $odd);
            $odd = !$odd;
        }
        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data));

        return $this->wrap($html, 'Relatório de Advertências', 'relatorio_advertencias_');
    }

    // ── Relatório: Personalizado ──────────────────────────────────────────────

    public function custom(array $data, array $filters): array
    {
        $html  = $this->header('Relatório Personalizado', 'Gerado em: ' . date('d/m/Y H:i'), $filters);

        if (empty($data)) {
            $html .= '<div style="text-align:center;padding:20px;color:' . self::C_MUTED . ';font-size:9pt;">Nenhum dado encontrado para os filtros informados.</div>';
            return $this->wrap($html, 'Relatório Personalizado', 'relatorio_personalizado_');
        }

        $columns  = array_keys((array) $data[0]);
        $colCount = count($columns);
        $colW     = max(5, (int) floor(100 / max($colCount, 1))) . '%';

        $ths = '';
        foreach ($columns as $col) {
            $ths .= '<th width="' . $colW . '" style="background:' . self::C_NAVY . ';color:#FFFFFF;font-weight:bold;font-size:7pt;padding:5px 3px;text-align:center;">'
                  . ucwords(str_replace('_', ' ', $col))
                  . '</th>';
        }

        $html .= $this->sectionTitle('Dados do Relatório (' . count($data) . ' registros)');
        $html .= '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">';
        $html .= '<thead><tr>' . $ths . '</tr></thead><tbody>';

        $odd = true;
        foreach ($data as $record) {
            $bg  = $odd ? self::C_STRIPE : self::C_WHITE;
            $tds = '';
            foreach ($columns as $col) {
                $val = is_array($record) ? ($record[$col] ?? '-') : ($record->$col ?? '-');
                if (is_bool($val)) $val = $val ? 'Sim' : 'Não';
                if (is_array($val) || is_object($val)) $val = '-';
                $tds .= '<td style="background:' . $bg . ';color:' . self::C_TEXT . ';font-size:7pt;padding:4px 3px;border-bottom:1px solid ' . self::C_BORDER . ';">' . esc((string)$val) . '</td>';
            }
            $html .= '<tr>' . $tds . '</tr>';
            $odd = !$odd;
        }

        $html .= '</tbody></table>';
        $html .= '<br>' . $this->footNote(count($data));

        return $this->wrap($html, 'Relatório Personalizado', 'relatorio_personalizado_');
    }
}

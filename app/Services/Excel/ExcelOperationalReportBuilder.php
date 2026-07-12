<?php

namespace App\Services\Excel;

use App\Services\Security\FormulaInjectionGuard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExcelOperationalReportBuilder
{
    public function __construct(private readonly ExcelSheetFormatter $formatter) {}

    private function fmtH(float $h): string
    {
        $sign = $h < 0 ? '-' : '';
        $abs  = abs($h);
        return $sign . (int)$abs . 'h' . str_pad((int)round(($abs - (int)$abs) * 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function fmtD(?string $d): string
    {
        if (empty($d)) return '-';
        $ts = strtotime((string)$d);
        return $ts ? date('d/m/Y', $ts) : (string)$d;
    }

    // ── Folha de Ponto ────────────────────────────────────────────────────────

    public function timesheet(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();

        // ── Aba Resumo ──
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');

        $row = $this->formatter->createHeader($summary, 'Relatório de Folha de Ponto');
        $row = $this->formatter->renderFilters($summary, $filters, $row);

        $totWork = array_sum(array_column($data, 'total_worked'));
        $totExp  = array_sum(array_column($data, 'expected'));
        $totExt  = array_sum(array_column($data, 'extra'));
        $totOwd  = array_sum(array_column($data, 'owed'));
        $bal     = $totWork - $totExp;

        $kpiData = [
            ['Indicador', 'Valor'],
            ['Total de registros',        count($data)],
            ['Total horas trabalhadas',   $this->fmtH($totWork)],
            ['Total horas previstas',     $this->fmtH($totExp)],
            ['Saldo total',               ($bal >= 0 ? '+' : '') . $this->fmtH($bal)],
            ['Total horas extras',        $this->fmtH($totExt)],
            ['Total horas débito',        $this->fmtH($totOwd)],
        ];
        $this->formatter->createTableHeader($summary, ['Indicador', 'Valor'], $row);
        $odd = true;
        foreach (array_slice($kpiData, 1) as $kpiRow) {
            $row++;
            $summary->setCellValue("A{$row}", $kpiRow[0]);
            $summary->setCellValue("B{$row}", $kpiRow[1]);
            $this->formatter->styleDataRow($summary, $row, 2, $odd);
            if (str_contains((string)$kpiRow[0], 'Saldo') && $bal != 0) {
                $bal > 0 ? $this->formatter->stylePositive($summary, "B{$row}")
                         : $this->formatter->styleNegative($summary, "B{$row}");
            }
            $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($summary, 'B');

        // ── Aba Detalhes ──
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detalhes');
        $headers = ['Data', 'Funcionário', 'Departamento', 'Entrada', 'Saída',
                    'Trabalhado', 'Previsto', 'Saldo', 'Extras', 'Débito', 'Observações'];
        $this->formatter->createTableHeader($detail, $headers, 1);
        $this->formatter->freezeHeaderRow($detail, 1);
        $detail->setAutoFilter('A1:K1');

        $row = 2; $odd = true;
        $totalsWork = $totalsExp = $totalsExt = $totalsOwd = 0.0;
        foreach ($data as $record) {
            $tw  = (float)($record['total_worked'] ?? 0);
            $exp = (float)($record['expected']     ?? 0);
            $ext = (float)($record['extra']        ?? 0);
            $owd = (float)($record['owed']         ?? 0);
            $b   = isset($record['balance']) ? (float)$record['balance'] : ($tw - $exp);
            $totalsWork += $tw; $totalsExp += $exp; $totalsExt += $ext; $totalsOwd += $owd;

            $detail->fromArray(FormulaInjectionGuard::neutralizeRow([
                $this->fmtD($record['date'] ?? null),
                $record['employee_name'] ?? '-',
                $record['department']    ?? '-',
                $record['first_punch']   ?? '-',
                $record['last_punch']    ?? '-',
                $this->fmtH($tw),
                $this->fmtH($exp),
                ($b >= 0 ? '+' : '') . $this->fmtH($b),
                $ext > 0 ? $this->fmtH($ext) : '-',
                $owd > 0 ? $this->fmtH($owd) : '-',
                $record['notes'] ?? '',
            ]), null, "A{$row}");
            $this->formatter->styleDataRow($detail, $row, 11, $odd);
            if ($b != 0) {
                $b > 0 ? $this->formatter->stylePositive($detail, "H{$row}")
                       : $this->formatter->styleNegative($detail, "H{$row}");
            }
            $row++; $odd = !$odd;
        }

        // Linha de totais
        $totBal = $totalsWork - $totalsExp;
        $this->formatter->createTotalsRow($detail, [
            'TOTAIS', '', '', '', '',
            $this->fmtH($totalsWork),
            $this->fmtH($totalsExp),
            ($totBal >= 0 ? '+' : '') . $this->fmtH($totBal),
            $this->fmtH($totalsExt),
            $this->fmtH($totalsOwd),
            '',
        ], $row, 11);
        $this->formatter->autoSizeColumns($detail, 'K');

        return $this->wrap($spreadsheet, 'relatorio_folha_ponto_');
    }

    // ── Horas Extras ──────────────────────────────────────────────────────────

    public function overtime(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');
        $row = $this->formatter->createHeader($summary, 'Relatório de Horas Extras');
        $row = $this->formatter->renderFilters($summary, $filters, $row);

        $totExt   = array_sum(array_column($data, 'extra'));
        $weekends = count(array_filter($data, fn($r) => !empty($r['is_weekend'])));

        $kpis = [
            ['Indicador', 'Valor'],
            ['Total de registros',      count($data)],
            ['Total horas extras',      $this->fmtH($totExt)],
            ['Total extras com 50%',    $this->fmtH($totExt * 1.5)],
            ['Ocorrências fim semana',  $weekends],
        ];
        $this->formatter->createTableHeader($summary, ['Indicador', 'Valor'], $row);
        $odd = true;
        foreach (array_slice($kpis, 1) as $k) {
            $row++;
            $summary->setCellValue("A{$row}", $k[0]);
            $summary->setCellValue("B{$row}", $k[1]);
            $this->formatter->styleDataRow($summary, $row, 2, $odd);
            $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($summary, 'B');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detalhes');
        $this->formatter->createTableHeader($detail, ['Data', 'Funcionário', 'Departamento', 'Trabalhado', 'Previsto', 'H. Extras', 'Extras 50%', 'Dia'], 1);
        $this->formatter->freezeHeaderRow($detail, 1);
        $detail->setAutoFilter('A1:H1');

        $row = 2; $odd = true; $totE = 0.0;
        foreach ($data as $record) {
            $ext = (float)($record['extra'] ?? 0);
            $tw  = (float)($record['total_worked'] ?? 0);
            $exp = (float)($record['expected']     ?? 0);
            $wk  = !empty($record['is_weekend']);
            $totE += $ext;

            $detail->fromArray(FormulaInjectionGuard::neutralizeRow([
                $this->fmtD($record['date'] ?? null),
                $record['employee_name'] ?? '-',
                $record['department']    ?? '-',
                $this->fmtH($tw),
                $this->fmtH($exp),
                $this->fmtH($ext),
                $this->fmtH($ext * 1.5),
                $wk ? 'Fim de semana' : 'Dia útil',
            ]), null, "A{$row}");
            $this->formatter->styleDataRow($detail, $row, 8, $odd);
            $this->formatter->stylePositive($detail, "F{$row}");
            $row++; $odd = !$odd;
        }
        $this->formatter->createTotalsRow($detail, ['TOTAIS', '', '', '', '', $this->fmtH($totE), $this->fmtH($totE * 1.5), ''], $row, 8);
        $this->formatter->autoSizeColumns($detail, 'H');

        return $this->wrap($spreadsheet, 'relatorio_horas_extras_');
    }

    // ── Faltas e Atrasos ──────────────────────────────────────────────────────

    public function absence(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');
        $row = $this->formatter->createHeader($summary, 'Relatório de Faltas e Atrasos');
        $row = $this->formatter->renderFilters($summary, $filters, $row);

        $faltas  = count(array_filter($data, fn($r) => ($r['type'] ?? '') === 'falta'));
        $atrasos = count($data) - $faltas;
        $justif  = count(array_filter($data, fn($r) => !empty($r['justified'])));

        $this->formatter->createTableHeader($summary, ['Indicador', 'Qtde'], $row);
        $odd = true;
        foreach ([['Total de ocorrências', count($data)], ['Faltas', $faltas], ['Atrasos', $atrasos], ['Justificados', $justif]] as $k) {
            $row++;
            $summary->setCellValue("A{$row}", $k[0]);
            $summary->setCellValue("B{$row}", $k[1]);
            $this->formatter->styleDataRow($summary, $row, 2, $odd);
            $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($summary, 'B');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detalhes');
        $this->formatter->createTableHeader($detail, ['Data', 'Funcionário', 'Departamento', 'Tipo', 'Horário', 'Esperado', 'Atraso (min)', 'Status'], 1);
        $this->formatter->freezeHeaderRow($detail, 1);
        $detail->setAutoFilter('A1:H1');

        $row = 2; $odd = true;
        foreach ($data as $record) {
            $detail->fromArray(FormulaInjectionGuard::neutralizeRow([
                $this->fmtD($record['date'] ?? null),
                $record['employee_name']  ?? '-',
                $record['department']     ?? '-',
                ucfirst((string)($record['type'] ?? '')),
                $record['punch_time']     ?? '-',
                $record['expected_time']  ?? '-',
                (int)($record['delay_minutes'] ?? 0),
                ($record['justified'] ?? false) ? 'Justificado' : 'Pendente',
            ]), null, "A{$row}");
            $this->formatter->styleDataRow($detail, $row, 8, $odd);
            $row++; $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($detail, 'H');

        return $this->wrap($spreadsheet, 'relatorio_faltas_atrasos_');
    }

    // ── Banco de Horas ────────────────────────────────────────────────────────

    public function bankHours(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');
        $row = $this->formatter->createHeader($summary, 'Relatório de Banco de Horas');
        $row = $this->formatter->renderFilters($summary, $filters, $row);

        $totExt = array_sum(array_column($data, 'extra_hours_balance'));
        $totOwd = array_sum(array_column($data, 'owed_hours_balance'));
        $totBal = $totExt - $totOwd;

        $this->formatter->createTableHeader($summary, ['Indicador', 'Valor'], $row);
        $odd = true;
        foreach ([['Funcionários', count($data)], ['Total Extras', $this->fmtH($totExt)], ['Total Débitos', $this->fmtH($totOwd)], ['Saldo Geral', ($totBal >= 0 ? '+' : '') . $this->fmtH($totBal)]] as $k) {
            $row++;
            $summary->setCellValue("A{$row}", $k[0]);
            $summary->setCellValue("B{$row}", $k[1]);
            $this->formatter->styleDataRow($summary, $row, 2, $odd);
            $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($summary, 'B');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Posição Individual');
        $this->formatter->createTableHeader($detail, ['Funcionário', 'Departamento', 'H. Extras Acum.', 'H. Débitos Acum.', 'Saldo Total', 'Situação'], 1);
        $this->formatter->freezeHeaderRow($detail, 1);
        $detail->setAutoFilter('A1:F1');

        $row = 2; $odd = true;
        $totE = $totO = 0.0;
        foreach ($data as $record) {
            $ext = (float)($record['extra_hours_balance'] ?? 0);
            $owd = (float)($record['owed_hours_balance']  ?? 0);
            $bal = $ext - $owd;
            $sit = $bal > 0 ? 'Credor' : ($bal < 0 ? 'Devedor' : 'Neutro');
            $totE += $ext; $totO += $owd;

            $detail->fromArray(FormulaInjectionGuard::neutralizeRow([
                $record['employee_name'] ?? '-',
                $record['department']    ?? '-',
                $this->fmtH($ext),
                $this->fmtH($owd),
                ($bal >= 0 ? '+' : '') . $this->fmtH($bal),
                $sit,
            ]), null, "A{$row}");
            $this->formatter->styleDataRow($detail, $row, 6, $odd);
            $bal > 0 ? $this->formatter->stylePositive($detail, "E{$row}")
                     : ($bal < 0 ? $this->formatter->styleNegative($detail, "E{$row}") : null);
            $row++; $odd = !$odd;
        }
        $totB = $totE - $totO;
        $this->formatter->createTotalsRow($detail, ['TOTAIS', '', $this->fmtH($totE), $this->fmtH($totO), ($totB >= 0 ? '+' : '') . $this->fmtH($totB), ''], $row, 6);
        $this->formatter->autoSizeColumns($detail, 'F');

        return $this->wrap($spreadsheet, 'relatorio_banco_horas_');
    }

    // ── Consolidado Mensal ────────────────────────────────────────────────────

    public function consolidated(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');
        $row = $this->formatter->createHeader($summary, 'Relatório Consolidado Mensal');
        $row = $this->formatter->renderFilters($summary, $filters, $row);

        $totWork = array_sum(array_column($data, 'total_worked'));
        $totExp  = array_sum(array_column($data, 'total_expected'));
        $totLate = array_sum(array_column($data, 'late_count'));
        $totAbs  = array_sum(array_column($data, 'absence_count'));

        $this->formatter->createTableHeader($summary, ['Indicador', 'Valor'], $row);
        $odd = true;
        foreach ([['Funcionários', count($data)], ['Total trabalhado', $this->fmtH($totWork)], ['Total previsto', $this->fmtH($totExp)], ['Atrasos totais', $totLate], ['Faltas totais', $totAbs]] as $k) {
            $row++;
            $summary->setCellValue("A{$row}", $k[0]);
            $summary->setCellValue("B{$row}", $k[1]);
            $this->formatter->styleDataRow($summary, $row, 2, $odd);
            $odd = !$odd;
        }
        $this->formatter->autoSizeColumns($summary, 'B');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Por Funcionário');
        $this->formatter->createTableHeader($detail, ['Funcionário', 'Departamento', 'Dias', 'Trabalhado', 'Previsto', 'Extras', 'Débitos', 'Saldo', 'Atrasos', 'Faltas', '% Presença'], 1);
        $this->formatter->freezeHeaderRow($detail, 1);
        $detail->setAutoFilter('A1:K1');

        $row = 2; $odd = true;
        $tW = $tE = $tEx = $tOw = 0.0; $tLate = $tAbs = 0;
        foreach ($data as $record) {
            $tw  = (float)($record['total_worked']   ?? 0);
            $exp = (float)($record['total_expected'] ?? 0);
            $ext = (float)($record['extra']  ?? 0);
            $owd = (float)($record['owed']   ?? 0);
            $bal = $ext - $owd;
            $pres = $exp > 0 ? round(($tw / $exp) * 100, 1) : 0;
            $tW += $tw; $tE += $exp; $tEx += $ext; $tOw += $owd;
            $tLate += (int)($record['late_count'] ?? 0);
            $tAbs  += (int)($record['absence_count'] ?? 0);

            $detail->fromArray(FormulaInjectionGuard::neutralizeRow([
                $record['employee_name'] ?? '-',
                $record['department']    ?? '-',
                (int)($record['days_worked'] ?? 0),
                $this->fmtH($tw),
                $this->fmtH($exp),
                $ext > 0 ? $this->fmtH($ext) : '-',
                $owd > 0 ? $this->fmtH($owd) : '-',
                ($bal >= 0 ? '+' : '') . $this->fmtH($bal),
                (int)($record['late_count']    ?? 0),
                (int)($record['absence_count'] ?? 0),
                $pres . '%',
            ]), null, "A{$row}");
            $this->formatter->styleDataRow($detail, $row, 11, $odd);
            $bal != 0 && ($bal > 0 ? $this->formatter->stylePositive($detail, "H{$row}") : $this->formatter->styleNegative($detail, "H{$row}"));
            $row++; $odd = !$odd;
        }
        $totBal = $tEx - $tOw;
        $this->formatter->createTotalsRow($detail, ['TOTAIS', '', '', $this->fmtH($tW), $this->fmtH($tE), $this->fmtH($tEx), $this->fmtH($tOw), ($totBal >= 0 ? '+' : '') . $this->fmtH($totBal), $tLate, $tAbs, ''], $row, 11);
        $this->formatter->autoSizeColumns($detail, 'K');

        return $this->wrap($spreadsheet, 'relatorio_consolidado_');
    }

    private function wrap(Spreadsheet $spreadsheet, string $prefix): array
    {
        $spreadsheet->setActiveSheetIndex(0);
        return ['success' => true, 'spreadsheet' => $spreadsheet, 'filename' => $prefix . date('Y-m-d_His') . '.xlsx'];
    }
}

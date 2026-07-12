<?php

namespace App\Services\TXT;

class TXTReportContentBuilder
{
    private TXTLineFormatter $formatter;

    public function __construct(?TXTLineFormatter $formatter = null)
    {
        $this->formatter = $formatter ?? new TXTLineFormatter();
    }

    public function build(string $type, array $data, array $filters, array $companyProfile): array
    {
        return match ($type) {
            'folha-ponto' => $this->buildTimesheet($data, $filters, $companyProfile),
            'horas-extras' => $this->buildOvertime($data, $filters, $companyProfile),
            'faltas-atrasos' => $this->buildAbsence($data, $filters, $companyProfile),
            'banco-horas' => $this->buildBankHours($data, $filters, $companyProfile),
            'consolidado-mensal' => $this->buildConsolidated($data, $filters, $companyProfile),
            'justificativas' => $this->buildJustifications($data, $filters, $companyProfile),
            'advertencias' => $this->buildWarnings($data, $filters, $companyProfile),
            'personalizado' => $this->buildCustom($data, $filters, $companyProfile),
            default => throw new \InvalidArgumentException('Tipo de relatório inválido'),
        };
    }

    private function buildTimesheet(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE FOLHA DE PONTO', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        $lines[] = sprintf('%-10s %-30s %-8s %-8s %-10s %-10s', 'DATA', 'FUNCIONÁRIO', 'ENTRADA', 'SAÍDA', 'TRABALHADO', 'SALDO');
        $lines[] = str_repeat('-', 80);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = sprintf(
                '%-10s %-30s %-8s %-8s %-10s %-10s',
                isset($record['date']) ? date('d/m/Y', strtotime((string) $record['date'])) : '-',
                isset($record['employee_name']) ? substr((string) $record['employee_name'], 0, 30) : '-',
                $record['first_punch'] ?? '-',
                $record['last_punch'] ?? '-',
                isset($record['total_worked']) ? number_format((float) $record['total_worked'], 2) . 'h' : '-',
                isset($record['balance']) ? (((float) $record['balance'] >= 0) ? '+' : '') . number_format((float) $record['balance'], 2) . 'h' : '-'
            );
        }

        $lines[] = str_repeat('=', 80);

        $totalWorked = array_sum(array_map(static fn($row) => (float) ((array) $row)['total_worked'] ?? 0, $data));
        $totalBalance = array_sum(array_map(static fn($row) => (float) ((array) $row)['balance'] ?? 0, $data));

        $lines[] = '';
        $lines[] = 'RESUMO:';
        $lines[] = '  Total Trabalhado: ' . number_format($totalWorked, 2) . 'h';
        $lines[] = '  Saldo Total: ' . ($totalBalance >= 0 ? '+' : '') . number_format($totalBalance, 2) . 'h';

        return $lines;
    }

    private function buildOvertime(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE HORAS EXTRAS', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        $lines[] = sprintf('%-10s %-30s %-10s %-15s', 'DATA', 'FUNCIONÁRIO', 'HORAS', 'TIPO');
        $lines[] = str_repeat('-', 80);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = sprintf(
                '%-10s %-30s %-10s %-15s',
                isset($record['date']) ? date('d/m/Y', strtotime((string) $record['date'])) : '-',
                isset($record['employee_name']) ? substr((string) $record['employee_name'], 0, 30) : '-',
                isset($record['overtime_hours']) ? number_format((float) $record['overtime_hours'], 2) . 'h' : '-',
                $record['overtime_type'] ?? '-'
            );
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildAbsence(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE FALTAS E ATRASOS', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        $lines[] = sprintf('%-10s %-30s %-10s %-25s', 'DATA', 'FUNCIONÁRIO', 'TIPO', 'OBSERVAÇÃO');
        $lines[] = str_repeat('-', 80);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = sprintf(
                '%-10s %-30s %-10s %-25s',
                isset($record['date']) ? date('d/m/Y', strtotime((string) $record['date'])) : '-',
                isset($record['employee_name']) ? substr((string) $record['employee_name'], 0, 30) : '-',
                $record['type'] ?? '-',
                isset($record['notes']) ? substr((string) $record['notes'], 0, 25) : '-'
            );
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildBankHours(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE BANCO DE HORAS', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        $lines[] = sprintf('%-30s %-15s %-15s %-15s', 'FUNCIONÁRIO', 'CRÉDITO', 'DÉBITO', 'SALDO');
        $lines[] = str_repeat('-', 80);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = sprintf(
                '%-30s %-15s %-15s %-15s',
                isset($record['employee_name']) ? substr((string) $record['employee_name'], 0, 30) : '-',
                isset($record['credit']) ? number_format((float) $record['credit'], 2) . 'h' : '-',
                isset($record['debit']) ? number_format((float) $record['debit'], 2) . 'h' : '-',
                isset($record['balance']) ? number_format((float) $record['balance'], 2) . 'h' : '-'
            );
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildConsolidated(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO CONSOLIDADO MENSAL', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        $lines[] = sprintf('%-25s %-10s %-10s %-10s %-10s', 'FUNCIONÁRIO', 'TRAB.', 'ESPER.', 'SALDO', 'DIAS');
        $lines[] = str_repeat('-', 80);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = sprintf(
                '%-25s %-10s %-10s %-10s %-10s',
                isset($record['employee_name']) ? substr((string) $record['employee_name'], 0, 25) : '-',
                isset($record['total_worked']) ? number_format((float) $record['total_worked'], 1) . 'h' : '-',
                isset($record['expected']) ? number_format((float) $record['expected'], 1) . 'h' : '-',
                isset($record['balance']) ? number_format((float) $record['balance'], 1) . 'h' : '-',
                $record['days_worked'] ?? '-'
            );
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildJustifications(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE JUSTIFICATIVAS', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = str_repeat('-', 80);
            $lines[] = 'Data: ' . (isset($record['date']) ? date('d/m/Y', strtotime((string) $record['date'])) : '-');
            $lines[] = 'Funcionário: ' . ($record['employee_name'] ?? '-');
            $lines[] = 'Tipo: ' . ($record['type'] ?? '-');
            $lines[] = 'Status: ' . ($record['status'] ?? '-');
            $lines[] = 'Justificativa: ' . ($record['justification'] ?? '-');
            $lines[] = '';
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildWarnings(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO DE ADVERTÊNCIAS', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = str_repeat('-', 80);
            $lines[] = 'Data: ' . (isset($record['date']) ? date('d/m/Y', strtotime((string) $record['date'])) : '-');
            $lines[] = 'Funcionário: ' . ($record['employee_name'] ?? '-');
            $lines[] = 'Tipo: ' . ($record['type'] ?? '-');
            $lines[] = 'Motivo: ' . ($record['reason'] ?? '-');
            $lines[] = '';
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }

    private function buildCustom(array $data, array $filters, array $companyProfile): array
    {
        $lines = [];
        $this->formatter->addCommonHeader($lines, 'RELATÓRIO PERSONALIZADO', $companyProfile);
        $this->formatter->addFiltersSection($lines, $filters);

        foreach ($data as $record) {
            $record = (array) $record;
            $lines[] = str_repeat('-', 80);
            foreach ($record as $key => $value) {
                $lines[] = ucfirst((string) $key) . ': ' . $value;
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('=', 80);
        return $lines;
    }
}

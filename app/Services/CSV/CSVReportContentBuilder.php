<?php

namespace App\Services\CSV;

class CSVReportContentBuilder
{
    /**
     * @return array{filename:string,headers:array,rows:array}
     */
    public function build(string $type, array $data, array $filters = []): array
    {
        return match ($type) {
            'folha-ponto' => $this->buildTimesheet($data),
            'horas-extras' => $this->buildOvertime($data),
            'faltas-atrasos' => $this->buildAbsence($data),
            'banco-horas' => $this->buildBankHours($data),
            'consolidado-mensal' => $this->buildConsolidated($data),
            'justificativas' => $this->buildJustifications($data),
            'advertencias' => $this->buildWarnings($data),
            'personalizado' => $this->buildCustom($data),
            default => throw new \InvalidArgumentException('Tipo de relatório inválido'),
        };
    }

    private function buildTimesheet(array $data): array
    {
        $headers = ['Data', 'Colaborador', 'Departamento', 'Entrada', 'Saída', 'Trabalhado (h)', 'Esperado (h)', 'Saldo (h)', 'Observações'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $balance = (float) ($r['balance'] ?? 0);
            $rows[] = [
                date('d/m/Y', strtotime((string) ($r['date'] ?? 'now'))),
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                $r['first_punch'] ?? '-',
                $r['last_punch'] ?? '-',
                number_format((float) ($r['total_worked'] ?? 0), 2, ',', '.'),
                number_format((float) ($r['expected'] ?? 0), 2, ',', '.'),
                ($balance > 0 ? '+' : '') . number_format($balance, 2, ',', '.'),
                $r['notes'] ?? '',
            ];
        }

        return $this->payload('relatorio_folha_ponto_', $headers, $rows);
    }

    private function buildOvertime(array $data): array
    {
        $headers = ['Data', 'Colaborador', 'Departamento', 'Trabalhado (h)', 'Esperado (h)', 'Extras (h)', 'Extra 50% (h)', 'Tipo'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $extra = (float) ($r['extra'] ?? 0);
            $rows[] = [
                date('d/m/Y', strtotime((string) ($r['date'] ?? 'now'))),
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                number_format((float) ($r['total_worked'] ?? 0), 2, ',', '.'),
                number_format((float) ($r['expected'] ?? 0), 2, ',', '.'),
                number_format($extra, 2, ',', '.'),
                number_format($extra * 1.5, 2, ',', '.'),
                !empty($r['is_weekend']) ? 'Fim de semana' : 'Dia útil',
            ];
        }

        return $this->payload('relatorio_horas_extras_', $headers, $rows);
    }

    private function buildAbsence(array $data): array
    {
        $headers = ['Data', 'Colaborador', 'Departamento', 'Tipo', 'Horário', 'Esperado', 'Atraso (min)', 'Status'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $rows[] = [
                date('d/m/Y', strtotime((string) ($r['date'] ?? 'now'))),
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                ucfirst((string) ($r['type'] ?? '')),
                $r['punch_time'] ?? '-',
                $r['expected_time'] ?? '-',
                (string) ($r['delay_minutes'] ?? '0'),
                !empty($r['justified']) ? 'Justificado' : 'Pendente',
            ];
        }

        return $this->payload('relatorio_faltas_atrasos_', $headers, $rows);
    }

    private function buildBankHours(array $data): array
    {
        $headers = ['Colaborador', 'Departamento', 'Extras Acumuladas (h)', 'Devidas Acumuladas (h)', 'Saldo Total (h)', 'Status'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $extra = (float) ($r['extra_hours_balance'] ?? 0);
            $owed = (float) ($r['owed_hours_balance'] ?? 0);
            $balance = $extra - $owed;
            $status = $balance > 0 ? 'Credor' : ($balance < 0 ? 'Devedor' : 'Neutro');

            $rows[] = [
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                number_format($extra, 2, ',', '.'),
                number_format($owed, 2, ',', '.'),
                ($balance > 0 ? '+' : '') . number_format($balance, 2, ',', '.'),
                $status,
            ];
        }

        return $this->payload('relatorio_banco_horas_', $headers, $rows);
    }

    private function buildConsolidated(array $data): array
    {
        $headers = ['Colaborador', 'Departamento', 'Dias Trabalhados', 'Horas Trabalhadas', 'Horas Esperadas', 'Horas Extras', 'Horas Devidas', 'Saldo', 'Atrasos', 'Faltas'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $extra = (float) ($r['extra'] ?? 0);
            $owed = (float) ($r['owed'] ?? 0);
            $balance = $extra - $owed;

            $rows[] = [
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                (string) ($r['days_worked'] ?? ''),
                number_format((float) ($r['total_worked'] ?? 0), 2, ',', '.'),
                number_format((float) ($r['total_expected'] ?? 0), 2, ',', '.'),
                number_format($extra, 2, ',', '.'),
                number_format($owed, 2, ',', '.'),
                ($balance > 0 ? '+' : '') . number_format($balance, 2, ',', '.'),
                (string) ($r['late_count'] ?? '0'),
                (string) ($r['absence_count'] ?? '0'),
            ];
        }

        return $this->payload('relatorio_consolidado_', $headers, $rows);
    }

    private function buildJustifications(array $data): array
    {
        $headers = ['Data', 'Colaborador', 'Tipo', 'Categoria', 'Motivo', 'Status', 'Possui Anexos', 'Criado em'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $rows[] = [
                date('d/m/Y', strtotime((string) ($r['justification_date'] ?? 'now'))),
                $r['employee_name'] ?? '',
                ucfirst(str_replace('-', ' ', (string) ($r['justification_type'] ?? ''))),
                ucfirst(str_replace('-', ' ', (string) ($r['category'] ?? ''))),
                $this->truncate((string) ($r['reason'] ?? ''), 200),
                ucfirst((string) ($r['status'] ?? '')),
                !empty($r['has_attachments']) ? 'Sim' : 'Não',
                date('d/m/Y H:i', strtotime((string) ($r['created_at'] ?? 'now'))),
            ];
        }

        return $this->payload('relatorio_justificativas_', $headers, $rows);
    }

    private function buildWarnings(array $data): array
    {
        $headers = ['Data', 'Colaborador', 'Departamento', 'Tipo', 'Motivo', 'Status', 'Emitido por'];
        $rows = [];

        foreach ($data as $record) {
            $r = $this->asArray($record);
            $rows[] = [
                date('d/m/Y', strtotime((string) ($r['date'] ?? 'now'))),
                $r['employee_name'] ?? '',
                $r['department'] ?? '',
                ucfirst((string) ($r['warning_type'] ?? '')),
                $this->truncate((string) ($r['reason'] ?? ''), 200),
                ucfirst((string) ($r['status'] ?? '')),
                $r['issued_by_name'] ?? '-',
            ];
        }

        return $this->payload('relatorio_advertencias_', $headers, $rows);
    }

    private function buildCustom(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Nenhum dado para exportar');
        }

        $firstRecord = $this->asArray($data[0]);
        $headers = array_map(static fn ($key) => ucfirst(str_replace('_', ' ', (string) $key)), array_keys($firstRecord));

        $rows = [];
        foreach ($data as $record) {
            $rows[] = array_values($this->asArray($record));
        }

        return $this->payload('relatorio_personalizado_', $headers, $rows);
    }

    private function payload(string $prefix, array $headers, array $rows): array
    {
        return [
            'filename' => $prefix . date('Y-m-d_His') . '.csv',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    private function asArray(mixed $record): array
    {
        return is_array($record) ? $record : (array) $record;
    }
}

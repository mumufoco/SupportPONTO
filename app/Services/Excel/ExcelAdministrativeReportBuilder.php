<?php

namespace App\Services\Excel;

use App\Services\Security\FormulaInjectionGuard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelAdministrativeReportBuilder
{
    public function __construct(private readonly ExcelSheetFormatter $formatter)
    {
    }

    public function justifications(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumo');
        $this->formatter->createHeader($summarySheet, 'Relatório de Justificativas');
        $this->formatter->renderFilters($summarySheet, $filters, 4);

        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Detalhes');
        $headers = ['Data', 'Colaborador', 'Tipo', 'Categoria', 'Motivo', 'Status', 'Anexos', 'Criado em'];
        $this->formatter->createTableHeader($detailsSheet, $headers, 1);

        $row = 2;
        foreach ($data as $record) {
            $detailsSheet->fromArray(FormulaInjectionGuard::neutralizeRow([
                date('d/m/Y', strtotime($record['justification_date'])),
                $record['employee_name'],
                ucfirst(str_replace('-', ' ', $record['justification_type'])),
                ucfirst(str_replace('-', ' ', $record['category'])),
                mb_substr($record['reason'], 0, 100) . '...',
                ucfirst($record['status']),
                $record['has_attachments'] ? 'Sim' : 'Não',
                date('d/m/Y H:i', strtotime($record['created_at'])),
            ]), null, "A{$row}");
            $row++;
        }

        $detailsSheet->setAutoFilter('A1:H' . ($row - 1));
        $this->formatter->autoSizeColumns($detailsSheet, 'H');

        return $this->wrap($spreadsheet, 'relatorio_justificativas_');
    }

    public function warnings(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumo');
        $this->formatter->createHeader($summarySheet, 'Relatório de Advertências');
        $this->formatter->renderFilters($summarySheet, $filters, 4);

        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Detalhes');
        $headers = ['Data', 'Colaborador', 'Departamento', 'Tipo', 'Motivo', 'Status', 'Emitido por'];
        $this->formatter->createTableHeader($detailsSheet, $headers, 1);

        $row = 2;
        foreach ($data as $record) {
            $detailsSheet->fromArray(FormulaInjectionGuard::neutralizeRow([
                date('d/m/Y', strtotime($record['date'])),
                $record['employee_name'],
                $record['department'],
                ucfirst($record['warning_type']),
                mb_substr($record['reason'], 0, 80) . '...',
                ucfirst($record['status']),
                $record['issued_by_name'] ?? '-',
            ]), null, "A{$row}");
            $row++;
        }

        $detailsSheet->setAutoFilter('A1:G' . ($row - 1));
        $this->formatter->autoSizeColumns($detailsSheet, 'G');

        return $this->wrap($spreadsheet, 'relatorio_advertencias_');
    }

    public function custom(array $data, array $filters): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dados');

        $this->formatter->createHeader($sheet, 'Relatório Personalizado');
        $this->formatter->renderFilters($sheet, $filters, 4);

        if (!empty($data)) {
            $columns = array_keys((array) $data[0]);
            $col = 'A';
            $row = 6;
            foreach ($columns as $column) {
                $sheet->setCellValue("{$col}{$row}", ucfirst(str_replace('_', ' ', $column)));
                $col++;
            }

            $this->formatter->styleHeaderRow($sheet, $row, count($columns));
            $row++;

            foreach ($data as $record) {
                $col = 'A';
                foreach ($columns as $column) {
                    $value = is_array($record) ? $record[$column] : $record->$column;
                    $sheet->setCellValue("{$col}{$row}", FormulaInjectionGuard::neutralize($value));
                    $col++;
                }
                $row++;
            }

            $lastCol = chr(65 + count($columns) - 1);
            $sheet->setAutoFilter("A6:{$lastCol}" . ($row - 1));
            $this->formatter->autoSizeColumns($sheet, $lastCol);
        }

        return $this->wrap($spreadsheet, 'relatorio_personalizado_');
    }

    private function wrap(Spreadsheet $spreadsheet, string $prefix): array
    {
        return [
            'success' => true,
            'spreadsheet' => $spreadsheet,
            'filename' => $prefix . date('Y-m-d_His') . '.xlsx',
        ];
    }
}

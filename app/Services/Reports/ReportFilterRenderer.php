<?php

namespace App\Services\Reports;

class ReportFilterRenderer
{
    public function applySpreadsheetHeader($sheet, string $title): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    }

    public function applySpreadsheetFilters($sheet, array $filters, int $startRow): void
    {
        $row = $startRow;
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $label = ucfirst(str_replace('_', ' ', (string) $key));
                $sheet->setCellValue("A{$row}", "{$label}:");
                $sheet->setCellValue("B{$row}", is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value);
                $row++;
            }
        }
    }

    public function renderHtmlFilters(array $filters): string
    {
        if (empty($filters)) {
            return '';
        }

        $skipKeys = ['limit', 'employee_ids', 'department_restriction_applied'];

        $html = '<p><strong>Filtros:</strong></p><ul>';
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || in_array($key, $skipKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $label = ucfirst(str_replace('_', ' ', (string) $key));
            $html .= '<li><strong>' . esc($label) . ':</strong> ' . esc((string) $value) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}

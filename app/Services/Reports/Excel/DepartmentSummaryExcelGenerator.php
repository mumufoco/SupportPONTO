<?php

namespace App\Services\Reports\Excel;

use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Services\TimesheetService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DepartmentSummaryExcelGenerator
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly SettingModel $settingModel,
        private readonly TimesheetService $timesheetService,
        private readonly ReportExcelValueFormatter $formatter,
        private readonly ?TimePunchModel $timePunchModel = null,
    ) {
    }

    public function generate(string $department, string $month): array
    {
        $employees = $this->employeeModel
            ->where('department', $department)
            ->where('active', true)
            ->where('role !=', 'admin')
            ->orderBy('name', 'ASC')
            ->findAll();

        if (empty($employees)) {
            return [
                'success' => false,
                'error' => 'Nenhum funcionário encontrado no departamento.',
            ];
        }

        $startDate = $month . '-01';
        $endDate   = date('Y-m-t', strtotime($startDate));

        // Batch-fetch all punches for all employees in one query — eliminates N+1
        // (was calculateHoursWorked + findLateArrivals = ~5 DB queries per employee)
        $punchModel   = $this->timePunchModel ?? model(TimePunchModel::class);
        $employeeIds  = array_column((array) $employees, 'id');
        [$rangeStart, $rangeEnd] = $punchModel->getDateRangeBounds($startDate, $endDate);
        $allPunches = $punchModel
            ->whereIn('employee_id', $employeeIds)
            ->where('punch_time >=', $rangeStart)
            ->where('punch_time <', $rangeEnd)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        // Group by employee_id → date → punch list
        $punchesByEmployee = [];
        foreach ($allPunches as $punch) {
            $date = substr((string) ($punch->punch_time ?? ''), 0, 10);
            $punchesByEmployee[(int) $punch->employee_id][$date][] = $punch;
        }

        // Late-arrival tolerance — fetch once, not per employee
        $toleranceMinutes = (int) $this->settingModel->get('late_tolerance_minutes', 10);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $companyName = $this->settingModel->get('company_name', 'Sistema de Ponto Eletrônico');
        $sheet->setCellValue('A1', $companyName);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', "RELATÓRIO DE HORAS - {$department}");
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Período: ' . date('m/Y', strtotime($month . '-01')));
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 5;
        $headers = ['Funcionário', 'Horas Trabalhadas', 'Horas Previstas', 'Saldo', 'Dias Trabalhados', 'Atrasos'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $col++;
        }

        $row++;

        foreach ($employees as $employee) {
            $empPunchesByDate = $punchesByEmployee[(int) $employee->id] ?? [];

            // Compute hours from pre-fetched punches — pure PHP, no DB queries
            $totalHours = 0.0;
            $totalDays  = 0;
            foreach ($empPunchesByDate as $dayPunches) {
                $dayCalc = $this->timesheetService->calculateDailyHours($dayPunches);
                $dayHours = (float) ($dayCalc['total_hours'] ?? 0);
                $totalHours += $dayHours;
                if ($dayHours > 0) {
                    $totalDays++;
                }
            }

            $expectedDailyHours = (float) ($employee->daily_hours ?? 8.00);
            $expectedTotalHours = $expectedDailyHours * $totalDays;
            $balance            = round($totalHours - $expectedTotalHours, 2);

            // Compute late arrivals from pre-fetched punches — pure PHP, no DB queries
            $lateCount = 0;
            if (!empty($employee->work_start_time)) {
                $expectedWithTolerance = date(
                    'H:i:s',
                    strtotime((string) $employee->work_start_time) + ($toleranceMinutes * 60)
                );
                foreach ($empPunchesByDate as $dayPunches) {
                    foreach ($dayPunches as $punch) {
                        if (($punch->punch_type ?? '') === 'entrada') {
                            $punchTime = date('H:i:s', strtotime((string) ($punch->punch_time ?? '')));
                            if ($punchTime > $expectedWithTolerance) {
                                $lateCount++;
                            }
                        }
                    }
                }
            }

            $sheet->setCellValue("A{$row}", $employee->name);
            $sheet->setCellValue("B{$row}", $this->formatter->hours(round($totalHours, 2)));
            $sheet->setCellValue("C{$row}", $this->formatter->hours(round($expectedTotalHours, 2)));
            $sheet->setCellValue("D{$row}", $this->formatter->balance($balance));
            $sheet->setCellValue("E{$row}", $totalDays);
            $sheet->setCellValue("F{$row}", $lateCount);

            foreach (['B', 'C', 'D', 'E', 'F'] as $numericCol) {
                $sheet->getStyle("{$numericCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($balance < 0) {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setRGB('FF0000');
            } elseif ($balance > 0) {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setRGB('008000');
            }

            $row++;
        }

        $sheet->getStyle('A5:F' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(12);

        $filename = "relatorio_departamento_{$department}_{$month}.xlsx";
        $filepath = WRITEPATH . 'uploads/reports/' . $filename;
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($filepath);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
        ];
    }
}

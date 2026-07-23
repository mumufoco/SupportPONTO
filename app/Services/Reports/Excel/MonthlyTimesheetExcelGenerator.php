<?php

namespace App\Services\Reports\Excel;

use App\Models\SettingModel;
use App\Services\TimesheetService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MonthlyTimesheetExcelGenerator
{
    public function __construct(
        private readonly TimesheetService $timesheetService,
        private readonly SettingModel $settingModel,
        private readonly ReportExcelValueFormatter $formatter,
    ) {
    }

    public function generate(int $employeeId, string $month): array
    {
        $timesheet = $this->timesheetService->generateMonthlyTimesheet($employeeId, $month);
        if (!($timesheet['success'] ?? false)) {
            return $timesheet;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $companyName = $this->settingModel->get('company_name', 'Sistema de Ponto Eletrônico');
        $spreadsheet->getProperties()
            ->setCreator($companyName)
            ->setTitle('Espelho de Ponto - ' . $timesheet['employee']['name'])
            ->setSubject('Relatório de Ponto Eletrônico')
            ->setDescription('Espelho de ponto mensal - ' . $month);

        $sheet->setCellValue('A1', $companyName);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'ESPELHO DE PONTO ELETRÔNICO');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 4;
        $sheet->setCellValue("A{$row}", 'Colaborador:');
        $sheet->setCellValue("B{$row}", $timesheet['employee']['name']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'CPF:');
        $sheet->setCellValue("B{$row}", $this->formatter->cpf($timesheet['employee']['cpf']));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Cargo:');
        $sheet->setCellValue("B{$row}", $timesheet['employee']['position']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Departamento:');
        $sheet->setCellValue("B{$row}", $timesheet['employee']['department']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Período:');
        $sheet->setCellValue("B{$row}", date('m/Y', strtotime($month . '-01')));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row += 2;
        $headerRow = $row;
        $headers = ['Data', 'Dia', 'Entrada', 'Saída', 'Int. Início', 'Int. Fim', 'Horas', 'Saldo'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $col++;
        }

        $row++;
        foreach ($timesheet['daily_records'] as $record) {
            $entrada = $saida = $intervaloInicio = $intervaloFim = '';
            foreach ($record['punches'] as $punch) {
                $time = date('H:i', strtotime($punch['time']));
                switch ($punch['type']) {
                    case 'entrada': $entrada = $time; break;
                    case 'saida': $saida = $time; break;
                    case 'intervalo_inicio': $intervaloInicio = $time; break;
                    case 'intervalo_fim': $intervaloFim = $time; break;
                }
            }

            $sheet->setCellValue("A{$row}", date('d/m', strtotime($record['date'])));
            $sheet->setCellValue("B{$row}", $this->formatter->dayOfWeekPt($record['day_of_week']));
            $sheet->setCellValue("C{$row}", $entrada);
            $sheet->setCellValue("D{$row}", $saida);
            $sheet->setCellValue("E{$row}", $intervaloInicio);
            $sheet->setCellValue("F{$row}", $intervaloFim);
            $sheet->setCellValue("G{$row}", $this->formatter->hours((float) $record['hours_worked']));
            $sheet->setCellValue("H{$row}", $this->formatter->balance((float) $record['balance']));

            foreach (range('A', 'H') as $col) {
                $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($record['balance'] < 0) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF0000');
            } elseif ($record['balance'] > 0) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('008000');
            }

            $row++;
        }

        $row++;
        $sheet->setCellValue("F{$row}", 'Total de Horas:');
        $sheet->setCellValue("G{$row}", $this->formatter->hours((float) $timesheet['summary']['total_hours']));
        $sheet->getStyle("F{$row}:G{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("F{$row}", 'Horas Previstas:');
        $sheet->setCellValue("G{$row}", $this->formatter->hours((float) $timesheet['summary']['expected_hours']));
        $sheet->getStyle("F{$row}:G{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("F{$row}", 'Saldo Total:');
        $sheet->setCellValue("G{$row}", $this->formatter->balance((float) $timesheet['summary']['balance']));
        $sheet->getStyle("F{$row}:G{$row}")->getFont()->setBold(true);
        if ($timesheet['summary']['balance'] < 0) {
            $sheet->getStyle("G{$row}")->getFont()->getColor()->setRGB('FF0000');
        } elseif ($timesheet['summary']['balance'] > 0) {
            $sheet->getStyle("G{$row}")->getFont()->getColor()->setRGB('008000');
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'NSR Inicial: ' . $timesheet['summary']['nsr_range']['first']);
        $sheet->setCellValue("D{$row}", 'NSR Final: ' . $timesheet['summary']['nsr_range']['last']);

        $lastDataRow = $row - 3;
        $sheet->getStyle("A{$headerRow}:H{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(10);

        $filename = "espelho_ponto_{$timesheet['employee']['name']}_{$month}.xlsx";
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

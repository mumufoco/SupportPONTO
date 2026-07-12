<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelFileExporter
{
    public function export(Spreadsheet $spreadsheet, string $filename): array
    {
        $year = date('Y');
        $month = date('m');
        $dir = WRITEPATH . "uploads/reports/{$year}/{$month}/";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . $filename;
        (new Xlsx($spreadsheet))->save($filepath);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => base_url("uploads/reports/{$year}/{$month}/{$filename}"),
            'size' => filesize($filepath),
        ];
    }
}

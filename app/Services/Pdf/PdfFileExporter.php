<?php

namespace App\Services\Pdf;

use TCPDF;

class PdfFileExporter
{
    public function export(TCPDF $pdf, string $filename): array
    {
        $year = date('Y');
        $month = date('m');
        $dir = WRITEPATH . "uploads/reports/{$year}/{$month}/";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . $filename;
        $pdf->Output($filepath, 'F');

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => base_url("uploads/reports/{$year}/{$month}/{$filename}"),
            'size' => filesize($filepath),
        ];
    }
}

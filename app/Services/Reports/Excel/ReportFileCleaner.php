<?php

namespace App\Services\Reports\Excel;

class ReportFileCleaner
{
    public function deleteOldReports(int $daysOld = 30): int
    {
        $reportsDir = WRITEPATH . 'uploads/reports/';
        if (! is_dir($reportsDir)) {
            return 0;
        }

        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $deleted = 0;

        foreach (scandir($reportsDir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $reportsDir . $file;
            if (is_file($filepath) && filemtime($filepath) < $cutoffTime && unlink($filepath)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

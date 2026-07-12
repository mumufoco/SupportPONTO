<?php

namespace App\Services\TXT;

class TXTFileWriter
{
    public function saveReportLines(array $lines, string $type): array
    {
        $filename = 'relatorio_' . $type . '_' . date('Y-m-d_His') . '.txt';
        return $this->persist($filename, $lines, "\n");
    }

    public function saveAfdLines(array $lines): array
    {
        $filename = 'AFD_' . date('YmdHis') . '.txt';
        return $this->persist($filename, $lines, "\r\n");
    }

    private function persist(string $filename, array $lines, string $lineEnding): array
    {
        $filepath = WRITEPATH . 'uploads/reports/' . $filename;

        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        file_put_contents($filepath, implode($lineEnding, $lines));

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
        ];
    }
}

<?php

namespace App\Services\CSV;

class CSVFileWriter
{
    public function __construct(private readonly CSVRowSerializer $serializer = new CSVRowSerializer())
    {
    }

    public function save(string $filename, array $headers, array $rows): array
    {
        $year = date('Y');
        $month = date('m');
        $dir = WRITEPATH . "uploads/reports/{$year}/{$month}/";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . $filename;
        $file = fopen($filepath, 'w');

        if (!$file) {
            return [
                'success' => false,
                'error' => 'Não foi possível criar o arquivo CSV',
            ];
        }

        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fwrite($file, $this->serializer->serialize($headers) . "\n");

        foreach ($rows as $row) {
            fwrite($file, $this->serializer->serialize($row) . "\n");
        }

        fclose($file);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => base_url("uploads/reports/{$year}/{$month}/{$filename}"),
            'size' => filesize($filepath),
        ];
    }
}

<?php

namespace App\Services;

use App\Services\CSV\CSVFileWriter;
use App\Services\CSV\CSVReportContentBuilder;

class CSVService
{
    private CSVReportContentBuilder $contentBuilder;
    private CSVFileWriter $fileWriter;

    public function __construct(
        ?CSVReportContentBuilder $contentBuilder = null,
        ?CSVFileWriter $fileWriter = null,
    ) {
        $this->contentBuilder = $contentBuilder ?? new CSVReportContentBuilder();
        $this->fileWriter = $fileWriter ?? new CSVFileWriter();
    }

    /**
     * @return array{success:bool,filename?:string,filepath?:string,url?:string,size?:int,error?:string,details?:string}
     */
    public function generateReport(string $type, array $data, array $filters = []): array
    {
        try {
            $payload = $this->contentBuilder->build($type, $data, $filters);
            return $this->fileWriter->save($payload['filename'], $payload['headers'], $payload['rows']);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'CSV generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar CSV',
                'details' => $e->getMessage(),
            ];
        }
    }
}

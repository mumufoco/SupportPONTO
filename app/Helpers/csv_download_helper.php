<?php

declare(strict_types=1);

use CodeIgniter\HTTP\ResponseInterface;

if (!function_exists('csv_download_response')) {
    /**
     * Gera uma resposta HTTP para download de CSV com BOM UTF-8.
     *
     * @param list<string> $headers
     * @param iterable<array<int, scalar|null>> $rows
     */
    function csv_download_response(ResponseInterface $response, string $filename, array $headers, iterable $rows): ResponseInterface
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível iniciar a geração do CSV.');
        }

        fwrite($stream, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn ($value) => $value ?? '', $row));
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content === false ? '' : $content);
    }
}

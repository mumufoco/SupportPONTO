<?php

namespace App\Services\Pdf;

/**
 * Cliente HTTP para o Gotenberg (conversor de HTML para PDF via Chromium
 * headless, rodando em Docker no próprio servidor — ver docker-compose,
 * container gotenberg/gotenberg:8, exposto em 127.0.0.1:3000).
 *
 * Substitui o TCPDF em todo o sistema: TCPDF::setHeaderHTMLStyleCSS()
 * (chamado em todo PDF que passava por PdfDocumentFactory) não existe na
 * versão da lib instalada — qualquer exportação em PDF quebrava com fatal
 * error. Gotenberg renderiza a MESMA string HTML já usada pelos
 * content builders (writeHTML), então a migração é só trocar o motor,
 * não reescrever o conteúdo.
 */
class GotenbergClient
{
    private string $baseUrl;
    private int $timeoutSeconds;

    public function __construct(?string $baseUrl = null, int $timeoutSeconds = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?? (env('GOTENBERG_URL') ?: 'http://127.0.0.1:3000'), '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param array{
     *   landscape?: bool,
     *   paperWidthInches?: float,
     *   paperHeightInches?: float,
     *   marginTopInches?: float,
     *   marginBottomInches?: float,
     *   marginLeftInches?: float,
     *   marginRightInches?: float,
     *   headerHtml?: string,
     *   footerHtml?: string,
     * } $options
     *
     * @throws \RuntimeException se o Gotenberg não responder 200.
     */
    public function htmlToPdf(string $html, array $options = []): string
    {
        $boundary = '----GotenbergBoundary' . bin2hex(random_bytes(16));
        $parts = [];

        $parts[] = $this->filePart($boundary, 'files', 'index.html', 'text/html', $html);

        if (! empty($options['headerHtml'])) {
            $parts[] = $this->filePart($boundary, 'files', 'header.html', 'text/html', (string) $options['headerHtml']);
        }
        if (! empty($options['footerHtml'])) {
            $parts[] = $this->filePart($boundary, 'files', 'footer.html', 'text/html', (string) $options['footerHtml']);
        }

        $fields = [
            'printBackground' => 'true',
            'preferCssPageSize' => 'false',
            'landscape' => (! empty($options['landscape'])) ? 'true' : 'false',
            'paperWidth' => (string) ($options['paperWidthInches'] ?? 8.27),
            'paperHeight' => (string) ($options['paperHeightInches'] ?? 11.69),
            'marginTop' => (string) ($options['marginTopInches'] ?? 0.4),
            'marginBottom' => (string) ($options['marginBottomInches'] ?? 0.4),
            'marginLeft' => (string) ($options['marginLeftInches'] ?? 0.4),
            'marginRight' => (string) ($options['marginRightInches'] ?? 0.4),
        ];

        foreach ($fields as $name => $value) {
            $parts[] = $this->fieldPart($boundary, $name, $value);
        }

        $body = implode('', $parts) . "--{$boundary}--\r\n";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/forms/chromium/convert/html',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Falha ao conectar ao serviço Gotenberg: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Gotenberg retornou HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 500));
        }

        return (string) $response;
    }

    private function filePart(string $boundary, string $fieldName, string $filename, string $contentType, string $content): string
    {
        return "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"{$fieldName}\"; filename=\"{$filename}\"\r\n"
            . "Content-Type: {$contentType}\r\n\r\n"
            . $content . "\r\n";
    }

    private function fieldPart(string $boundary, string $name, string $value): string
    {
        return "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n"
            . $value . "\r\n";
    }
}

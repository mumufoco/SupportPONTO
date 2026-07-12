<?php

namespace App\Services\SMS;

class AWSRequestSigner
{
    public function signSNSPost(string $endpoint, array $params, string $accessKey, string $secretKey, string $region): array
    {
        $service = 'sns';
        $algorithm = 'AWS4-HMAC-SHA256';
        $dateTime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $parsedUrl = parse_url($endpoint);
        $host = (string) ($parsedUrl['host'] ?? '');
        $uri = (string) ($parsedUrl['path'] ?? '/');

        ksort($params);
        $canonicalQueryString = http_build_query($params);

        $canonicalHeaders = "content-type:application/x-www-form-urlencoded\n";
        $canonicalHeaders .= "host:{$host}\n";
        $canonicalHeaders .= "x-amz-date:{$dateTime}\n";

        $signedHeaders = 'content-type;host;x-amz-date';
        $payloadHash = hash('sha256', $canonicalQueryString);

        $canonicalRequest = implode("\n", [
            'POST',
            $uri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $dateTime,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host' => $host,
            'X-Amz-Date' => $dateTime,
            'Authorization' => $authorization,
        ];
    }
}

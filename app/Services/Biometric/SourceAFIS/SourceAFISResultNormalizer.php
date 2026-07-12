<?php

namespace App\Services\Biometric\SourceAFIS;

class SourceAFISResultNormalizer
{
    public function verify(array $extractResult, array $compareResult, float $threshold): array
    {
        if (!($extractResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $extractResult['error'] ?? 'Failed to extract template',
            ];
        }

        if (!($compareResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $compareResult['error'] ?? 'Failed to compare templates',
            ];
        }

        $similarity = (float) ($compareResult['similarity'] ?? 0.0);

        return [
            'success' => true,
            'match' => $similarity >= $threshold,
            'similarity' => $similarity,
            'threshold' => $threshold,
        ];
    }

    public function apiFailure(array $response, string $context): array
    {
        if (!($response['request_success'] ?? false)) {
            $exception = $response['exception'] ?? null;
            $details = $exception instanceof \Throwable ? $exception->getMessage() : 'unknown';

            return [
                'success' => false,
                'error' => 'API error: ' . $details,
            ];
        }

        if (($response['status_code'] ?? 0) !== 200) {
            return [
                'success' => false,
                'error' => $response['body']['error'] ?? sprintf('%s request failed', $context),
            ];
        }

        return ['success' => true];
    }

    public function health(string $mode, array $response): array
    {
        $result = [
            'success' => true,
            'mode' => $mode,
        ];

        if ($mode !== 'api') {
            return $result;
        }

        if (!($response['request_success'] ?? false)) {
            $exception = $response['exception'] ?? null;
            $result['success'] = false;
            $result['api_available'] = false;
            $result['error'] = 'Cannot connect to SourceAFIS API: ' . (($exception instanceof \Throwable) ? $exception->getMessage() : 'unknown');

            return $result;
        }

        $result['api_available'] = ($response['status_code'] ?? 0) === 200;
        if (!$result['api_available']) {
            $result['success'] = false;
            $result['error'] = 'SourceAFIS API is not responding';
        }

        return $result;
    }
}

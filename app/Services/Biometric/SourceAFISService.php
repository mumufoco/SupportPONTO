<?php

namespace App\Services\Biometric;

use App\Services\Biometric\SourceAFIS\SourceAFISApiClient;
use App\Services\Biometric\SourceAFIS\SourceAFISInputPreparer;
use App\Services\Biometric\SourceAFIS\SourceAFISNativeMatcher;
use App\Services\Biometric\SourceAFIS\SourceAFISResultNormalizer;
use App\Support\BootstrapEnv;

/**
 * SourceAFIS Service
 *
 * Facade para matching biométrico de digitais, delegando preparação de entrada,
 * integração externa, comparação nativa e normalização para componentes coesos.
 */
class SourceAFISService
{
    private string $mode;
    private string $apiUrl;
    private int $timeout;
    private float $threshold;

    private SourceAFISApiClient $apiClient;
    private SourceAFISInputPreparer $inputPreparer;
    private SourceAFISNativeMatcher $nativeMatcher;
    private SourceAFISResultNormalizer $resultNormalizer;

    public function __construct(?string $mode = null)
    {
        $this->mode = $mode ?? BootstrapEnv::get('SOURCEAFIS_MODE', BootstrapEnv::get('sourceafis.mode', 'native'), ['sourceafis.mode']) ?? 'native';
        $this->apiUrl = BootstrapEnv::get('SOURCEAFIS_API_URL', BootstrapEnv::get('sourceafis.api_url', 'http://localhost:5001'), ['sourceafis.api_url']) ?? 'http://localhost:5001';
        $this->timeout = (int) (BootstrapEnv::get('SOURCEAFIS_TIMEOUT', BootstrapEnv::get('sourceafis.timeout', '30'), ['sourceafis.timeout']) ?? '30');
        $this->threshold = (float) (BootstrapEnv::get('SOURCEAFIS_THRESHOLD', BootstrapEnv::get('sourceafis.threshold', '0.40'), ['sourceafis.threshold']) ?? '0.40');

        $this->apiClient = new SourceAFISApiClient($this->apiUrl, $this->timeout);
        $this->inputPreparer = new SourceAFISInputPreparer();
        $this->nativeMatcher = new SourceAFISNativeMatcher();
        $this->resultNormalizer = new SourceAFISResultNormalizer();
    }

    public function extractTemplate(string $imagePath): array
    {
        $input = $this->inputPreparer->validateImagePath($imagePath);
        if (!($input['success'] ?? false)) {
            return $input;
        }

        return match ($this->mode) {
            'api' => $this->extractTemplateAPI($imagePath),
            'native' => $this->nativeMatcher->extractTemplate($imagePath),
            default => $this->extractTemplateMock($imagePath),
        };
    }

    public function compareTemplates(string $template1, string $template2): array
    {
        return match ($this->mode) {
            'api' => $this->compareTemplatesAPI($template1, $template2),
            'native' => $this->nativeMatcher->compareTemplates($template1, $template2, $this->threshold),
            default => $this->compareTemplatesMock($template1, $template2),
        };
    }

    public function verify(string $imagePath, string $storedTemplate): array
    {
        $extractResult = $this->extractTemplate($imagePath);
        $compareResult = ['success' => false, 'error' => 'Failed to compare templates'];

        if (($extractResult['success'] ?? false) === true && isset($extractResult['template'])) {
            $compareResult = $this->compareTemplates((string) $extractResult['template'], $storedTemplate);
        }

        return $this->resultNormalizer->verify($extractResult, $compareResult, $this->threshold);
    }

    public function health(): array
    {
        $response = ['request_success' => true, 'status_code' => 200, 'body' => []];
        if ($this->mode === 'api') {
            $response = $this->apiClient->health();
        }

        return $this->resultNormalizer->health($this->mode, $response);
    }

    private function extractTemplateAPI(string $imagePath): array
    {
        $imageBase64 = $this->inputPreparer->encodeImageToBase64($imagePath);
        if ($imageBase64 === false) {
            return [
                'success' => false,
                'error' => 'API error: Failed to read image file',
            ];
        }

        $response = $this->apiClient->extract($imageBase64);
        $failure = $this->resultNormalizer->apiFailure($response, 'API');
        if (!($failure['success'] ?? false)) {
            return $failure;
        }

        return [
            'success' => true,
            'template' => $response['body']['template'] ?? null,
            'minutiae_count' => $response['body']['minutiae_count'] ?? null,
        ];
    }

    private function compareTemplatesAPI(string $template1, string $template2): array
    {
        $response = $this->apiClient->compare($template1, $template2);
        $failure = $this->resultNormalizer->apiFailure($response, 'API');
        if (!($failure['success'] ?? false)) {
            return $failure;
        }

        return [
            'success' => true,
            'similarity' => (float) ($response['body']['similarity'] ?? 0),
            'match' => (bool) ($response['body']['match'] ?? false),
        ];
    }

    private function extractTemplateMock(string $imagePath): array
    {
        $hash = hash_file('sha256', $imagePath);

        return [
            'success' => true,
            'template' => $hash,
            'minutiae_count' => 42,
        ];
    }

    private function compareTemplatesMock(string $template1, string $template2): array
    {
        $similarity = $template1 === $template2 ? 1.0 : 0.0;

        return [
            'success' => true,
            'similarity' => $similarity,
            'match' => $similarity >= $this->threshold,
        ];
    }
}

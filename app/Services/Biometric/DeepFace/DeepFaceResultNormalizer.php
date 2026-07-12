<?php

namespace App\Services\Biometric\DeepFace;

class DeepFaceResultNormalizer
{
    public function connectionFailure(string $context, array $response): array
    {
        $exception = $response['exception'] ?? null;
        $message = $exception instanceof \Throwable ? $exception->getMessage() : 'unknown';
        log_message('error', sprintf('DeepFace %s error: %s', $context, $message));

        return [
            'success' => false,
            'error' => 'Serviço de reconhecimento facial temporariamente indisponível.',
            'code' => 'deepface_unavailable',
        ];
    }

    public function health(array $response): array
    {
        if (!$response['request_success']) {
            $exception = $response['exception'] ?? null;
            $details = $exception instanceof \Throwable ? $exception->getMessage() : 'unknown';

            log_message('error', 'DeepFace health check failed: ' . $details);

            return [
                'success' => false,
                'error' => 'Não foi possível conectar ao serviço de reconhecimento facial.',
                'details' => $details,
            ];
        }

        $body = $response['body'];

        return [
            'success' => $response['status_code'] === 200,
            'status' => $body['status'] ?? 'unknown',
            'version' => $body['version'] ?? null,
            'models_loaded' => $body['models_loaded'] ?? false,
        ];
    }

    public function enroll(array $response): array
    {
        if (!$response['request_success']) {
            return $this->connectionFailure('enrollment', $response);
        }

        $body = $response['body'];
        if ($response['status_code'] !== 200 && $response['status_code'] !== 201) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Erro ao processar imagem.',
                'details' => $body['details'] ?? null,
            ];
        }

        if (!($body['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Falha no cadastro facial.',
            ];
        }

        return [
            'success' => true,
            'image_hash' => $body['image_hash'] ?? null,
            'confidence' => $body['confidence'] ?? 0.95,
            'facial_area' => $body['facial_area'] ?? null,
        ];
    }

    public function recognize(array $response, float $threshold): array
    {
        if (!$response['request_success']) {
            return $this->connectionFailure('recognition', $response);
        }

        $body = $response['body'];
        if ($response['status_code'] !== 200) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Erro ao processar imagem.',
                'details' => $body['details'] ?? null,
            ];
        }

        if (!($body['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Falha no reconhecimento.',
            ];
        }

        if (!($body['recognized'] ?? false)) {
            return [
                'success' => true,
                'recognized' => false,
                'message' => 'Nenhum rosto reconhecido.',
            ];
        }

        return [
            'success' => true,
            'recognized' => true,
            'employee_id' => $body['employee_id'] ?? null,
            'similarity' => $body['similarity'] ?? 0,
            'distance' => $body['distance'] ?? 1,
            'model' => $body['model'] ?? 'VGG-Face',
            'threshold_used' => $threshold,
        ];
    }

    public function verify(array $response): array
    {
        if (!$response['request_success']) {
            return $this->connectionFailure('verification', $response);
        }

        $body = $response['body'];
        if ($response['status_code'] !== 200) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Erro ao processar imagem.',
            ];
        }

        return [
            'success' => true,
            'verified' => $body['verified'] ?? false,
            'similarity' => $body['similarity'] ?? 0,
            'distance' => $body['distance'] ?? 1.0,
            'threshold' => $body['threshold'] ?? 0.40,
        ];
    }

    public function analyze(array $response): array
    {
        if (!$response['request_success']) {
            return $this->connectionFailure('analysis', $response);
        }

        $body = $response['body'];
        if ($response['status_code'] !== 200) {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Erro ao processar imagem.',
            ];
        }

        return [
            'success' => true,
            'analysis' => $body['analysis'] ?? [],
        ];
    }
}

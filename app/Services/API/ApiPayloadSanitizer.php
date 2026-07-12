<?php

namespace App\Services\API;

use App\Services\Security\InputSanitizerService;

/**
 * Sanitização específica para payloads de API.
 *
 * Mantém intactos segredos, tokens, senhas, imagens/base64 e templates biométricos,
 * mas remove HTML/controle de campos textuais comuns antes de chegarem aos services.
 */
class ApiPayloadSanitizer
{
    public function __construct(private readonly InputSanitizerService $sanitizer = new InputSanitizerService())
    {
    }

    public function sanitizeValue(mixed $value, string $field = ''): mixed
    {
        return $this->sanitizer->sanitize($value, $field);
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    public function sanitizePayload(array $payload): array
    {
        return $this->sanitizer->sanitizeArray($payload);
    }

    public function sanitizeQueryValue(mixed $value, string $field = ''): mixed
    {
        if (is_string($value)) {
            return $this->sanitizer->sanitizeText($value, 500);
        }

        if (is_array($value)) {
            return $this->sanitizer->sanitizeArray($value, $field);
        }

        return $value;
    }
}

<?php

namespace App\Services\Security;

/**
 * Sanitização defensiva de entrada textual para reduzir XSS armazenado/refletido.
 *
 * A saída continua devendo usar esc()/sp_attr()/sp_js(), mas esta camada remove
 * tags, bytes de controle e esquemas perigosos antes de persistir dados comuns.
 */
class InputSanitizerService
{
    private const DEFAULT_MAX_LENGTH = 5000;

    /**
     * Campos em que o valor original não deve ser alterado por sanitização textual.
     * Senhas, tokens, base64 e templates biométricos são validados por políticas
     * próprias e podem ser corrompidos por strip_tags/normalização.
     */
    private const SENSITIVE_FIELD_PATTERNS = [
        '/password/i',
        '/passwd/i',
        '/passphrase/i',
        '/token/i',
        '/secret/i',
        '/signature/i',
        '/csrf/i',
        '/photo/i',
        '/image/i',
        '/base64/i',
        '/template/i',
        '/certificate/i',
        '/private_key/i',
        '/public_key/i',
        '/fingerprint/i',
        '/biometric/i',
    ];

    /**
     * @param mixed $value
     * @return mixed
     */
    public function sanitize(mixed $value, string $field = ''): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value, $field);
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($this->isSensitiveField($field)) {
            return $value;
        }

        return $this->sanitizeText($value);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function sanitizeArray(array $data, string $prefix = ''): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            $sanitized[$key] = $this->sanitize($value, $field);
        }

        return $sanitized;
    }

    public function sanitizeText(string $value, int $maxLength = self::DEFAULT_MAX_LENGTH): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = strip_tags($value);
        $value = preg_replace('/(?:javascript|data|vbscript)\s*:/iu', '', $value) ?? $value;
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    public function sanitizeFlashMessage(mixed $message): string
    {
        if (is_array($message)) {
            $message = implode(' ', array_map(static fn ($item): string => (string) $item, $message));
        }

        return $this->sanitizeText((string) $message, 1200);
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = $this->sanitizeText($filename, 180);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? 'arquivo';
        $filename = trim($filename, '._-');

        return $filename !== '' ? $filename : 'arquivo';
    }

    private function isSensitiveField(string $field): bool
    {
        foreach (self::SENSITIVE_FIELD_PATTERNS as $pattern) {
            if (preg_match($pattern, $field) === 1) {
                return true;
            }
        }

        return false;
    }
}

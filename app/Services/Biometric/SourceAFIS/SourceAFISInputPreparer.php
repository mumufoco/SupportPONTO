<?php

namespace App\Services\Biometric\SourceAFIS;

class SourceAFISInputPreparer
{
    public function validateImagePath(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'error' => 'Image file not found',
            ];
        }

        return [
            'success' => true,
            'path' => $imagePath,
        ];
    }

    public function encodeImageToBase64(string $imagePath): string|false
    {
        $content = file_get_contents($imagePath);
        if ($content === false) {
            return false;
        }

        return base64_encode($content);
    }
}

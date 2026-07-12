<?php

namespace App\Services\Biometric\Face;

class FaceImageService
{
    private int $maxImageSize;
    private int $minImageSize;
    private int $maxPixels;
    /** @var array<int, string> */
    private array $allowedMimeTypes;

    public function __construct(int $maxImageSize = 0, array $allowedMimeTypes = ['image/jpeg', 'image/png'])
    {
        $this->maxImageSize = $maxImageSize > 0 ? $maxImageSize : (int) env('BIOMETRIC_FACE_MAX_BYTES', 3145728);
        $this->minImageSize = (int) env('BIOMETRIC_FACE_MIN_BYTES', 1000);
        $this->maxPixels = (int) env('BIOMETRIC_FACE_MAX_PIXELS', 6000000);
        $this->allowedMimeTypes = $allowedMimeTypes;
    }

    public function cleanBase64(string $base64): string
    {
        $base64 = trim($base64);
        if (preg_match('/^data:(image\/(?:jpeg|jpg|png));base64,/i', $base64) === 1) {
            $base64 = preg_replace('/^data:image\/(?:jpeg|jpg|png);base64,/i', '', $base64) ?? $base64;
        }

        return preg_replace('/\s+/', '', $base64) ?? $base64;
    }

    public function validateImage(string $base64): array
    {
        $imageData = $this->cleanBase64($base64);

        if ($imageData === '' || preg_match('/^[A-Za-z0-9+\/]+=*$/', $imageData) !== 1) {
            return ['valid' => false, 'error' => 'Imagem facial deve ser enviada em base64 válido.'];
        }

        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            return ['valid' => false, 'error' => 'Base64 inválido.'];
        }

        $size = strlen($decoded);
        if ($size > $this->maxImageSize) {
            return ['valid' => false, 'error' => 'Imagem muito grande. Máximo: ' . $this->formatBytes($this->maxImageSize) . '.'];
        }

        if ($size < $this->minImageSize) {
            return ['valid' => false, 'error' => 'Imagem muito pequena ou corrompida.'];
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($decoded);
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            return ['valid' => false, 'error' => 'Formato inválido. Use JPG ou PNG.'];
        }

        $imageInfo = @getimagesizefromstring($decoded);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'Imagem inválida ou corrompida.'];
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0 || ($width * $height) > $this->maxPixels) {
            return ['valid' => false, 'error' => 'Dimensões da imagem facial excedem o limite permitido.'];
        }

        return ['valid' => true, 'size' => $size, 'mime_type' => $mimeType, 'width' => $width, 'height' => $height];
    }

    public function storeBackupImage(string $storagePath, int $employeeId, string $photoBase64): array
    {
        $decoded = base64_decode($this->cleanBase64($photoBase64), true);
        if (!is_string($decoded)) {
            return ['success' => false, 'error' => 'Imagem facial inválida.'];
        }

        if (!is_dir($storagePath) && !@mkdir($storagePath, 0750, true)) {
            return ['success' => false, 'error' => 'Diretório de armazenamento facial indisponível.'];
        }

        $imageHash = hash('sha256', $decoded);
        $randomSuffix = bin2hex(random_bytes(8));
        $filename = sprintf('employee_%d_face_%s_%s.jpg', $employeeId, substr($imageHash, 0, 16), $randomSuffix);
        $filePath = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!file_put_contents($filePath, $decoded, LOCK_EX)) {
            return ['success' => false, 'error' => 'Erro ao salvar imagem facial.'];
        }

        @chmod($filePath, 0640);

        return ['success' => true, 'file_path' => $filePath, 'image_hash' => $imageHash];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . 'MB';
        }

        return $bytes . ' bytes';
    }
}

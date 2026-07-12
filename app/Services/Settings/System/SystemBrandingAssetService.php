<?php

namespace App\Services\Settings\System;

use App\Models\SettingModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class SystemBrandingAssetService
{
    private const PUBLIC_UPLOAD_ROOT = 'uploads/system';
    private const BRANDING_DIR = 'branding';
    private const GENERAL_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'ico', 'webp'];
    private const GENERAL_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/webp',
    ];
    private const BACKGROUND_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const BACKGROUND_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __construct(private readonly SettingModel $settingModel = new SettingModel())
    {
    }

    public function processGeneralAssets(mixed $logoFile, mixed $faviconFile, mixed $bgFile): array
    {
        helper('file_upload');
        $errors        = [];
        $filesToProcess = [];

        $this->validateAsset($logoFile,    self::GENERAL_ALLOWED_EXTENSIONS,    self::GENERAL_ALLOWED_MIMES,    2097152, 'Logo',    'logos',       'logo',    $errors, $filesToProcess, 'company_logo');
        $this->validateAsset($faviconFile, self::GENERAL_ALLOWED_EXTENSIONS,    self::GENERAL_ALLOWED_MIMES,    1048576, 'Favicon', 'favicons',    'favicon', $errors, $filesToProcess, 'company_favicon');
        $this->validateAsset($bgFile,      self::BACKGROUND_ALLOWED_EXTENSIONS, self::BACKGROUND_ALLOWED_MIMES, 5242880, 'Capa',    'backgrounds', 'bg',      $errors, $filesToProcess, 'login_background');

        if ($errors !== []) {
            return ['success' => false, 'status' => 422, 'message' => implode('; ', $errors)];
        }

        foreach ($filesToProcess as $settingKey => $data) {
            $publicDir = $this->publicDirectoryPath($data['dir']);
            if (!is_dir($publicDir) && !mkdir($publicDir, 0755, true) && !is_dir($publicDir)) {
                return ['success' => false, 'status' => 500, 'message' => 'Falha ao preparar diretório de upload.'];
            }

            $oldPath = (string) $this->settingModel->get($settingKey, '');
            $this->deletePublicAsset($oldPath);

            $stored = supportponto_store_public_image_asset(
                $data['file'],
                $publicDir,
                $data['prefix'],
                $data['allowed_extensions'],
                $data['allowed_mimes'],
                $data['max_size']
            );

            if (($stored['success'] ?? false) !== true) {
                return ['success' => false, 'status' => 422, 'message' => $stored['message'] ?? 'Falha no upload seguro do arquivo.'];
            }

            $relativePath = $this->relativePublicPath($data['dir'], $stored['filename']);

            // Persist primary key
            $this->settingModel->setSetting($settingKey, $relativePath, 'string', 'general');

            // When updating logo, keep all variant keys and logo_path in sync
            if ($settingKey === 'company_logo') {
                foreach (['company_logo_original', 'company_logo_crop', 'company_logo_small', 'logo_path'] as $aliasKey) {
                    $this->settingModel->setSetting($aliasKey, $relativePath, 'string', 'general');
                }
                $this->syncDesignSystemLogo($relativePath);
            }

            // When updating favicon, keep favicon_path in sync
            if ($settingKey === 'company_favicon') {
                $this->settingModel->setSetting('favicon_path', $relativePath, 'string', 'general');
                $this->syncDesignSystemFavicon($relativePath);
            }

            // When updating login background, keep login_background_path in sync
            if ($settingKey === 'login_background') {
                $this->settingModel->setSetting('login_background_path', $relativePath, 'string', 'general');
                $this->syncDesignSystemField('login_background', base_url($relativePath));
            }
        }

        $this->settingModel->clearCache();

        return ['success' => true, 'message' => 'Configurações gerais salvas'];
    }

    public function saveLogoAssets(mixed $logoFile, ?string $cropData): array
    {
        helper('file_upload');

        if (!$logoFile instanceof UploadedFile || !$logoFile->isValid() || $logoFile->hasMoved()) {
            return ['success' => false, 'status' => 422, 'message' => 'Arquivo de logo é obrigatório.'];
        }

        $ext = strtolower((string) $logoFile->getExtension());
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            return ['success' => false, 'status' => 422, 'message' => 'Formato inválido. Use PNG/JPG/WEBP.'];
        }

        $realLogoMime = supportponto_detect_real_mime($logoFile->getTempName());
        if ($realLogoMime === null || !in_array($realLogoMime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return ['success' => false, 'status' => 422, 'message' => 'Tipo MIME inválido para a logo.'];
        }

        $size = @getimagesize($logoFile->getTempName());
        if (!$size || ($size[0] < 512 || $size[1] < 512)) {
            return ['success' => false, 'status' => 422, 'message' => 'Logo em baixa qualidade. Use imagem com mínimo 512x512.'];
        }

        $brandDir = $this->publicDirectoryPath(self::BRANDING_DIR);
        if (!is_dir($brandDir) && !mkdir($brandDir, 0755, true) && !is_dir($brandDir)) {
            return ['success' => false, 'status' => 500, 'message' => 'Falha ao preparar diretório da marca.'];
        }

        $this->deleteStoredLogoVariants();

        $stored = supportponto_store_public_image_asset($logoFile, $brandDir, 'logo_original', ['png', 'jpg', 'jpeg', 'webp'], ['image/png', 'image/jpeg', 'image/webp'], 5 * 1024 * 1024);
        if (($stored['success'] ?? false) !== true) {
            return ['success' => false, 'status' => 422, 'message' => $stored['message'] ?? 'Falha ao salvar logo.'];
        }

        $originalName = $stored['filename'];
        $cropName     = null;
        $smallName    = null;

        if (!empty($cropData) && str_starts_with($cropData, 'data:image/')) {
            [, $binary]  = explode(',', $cropData, 2);
            $decoded     = base64_decode($binary, true);
            if ($decoded === false || strlen($decoded) > 5 * 1024 * 1024) {
                return ['success' => false, 'status' => 422, 'message' => 'Dados de recorte inválidos.'];
            }

            $imageInfo = @getimagesizefromstring($decoded);
            if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return ['success' => false, 'status' => 422, 'message' => 'Prévia de recorte inválida.'];
            }

            $cropName = 'logo_crop_' . bin2hex(random_bytes(16)) . '.png';
            $cropPath = $brandDir . DIRECTORY_SEPARATOR . $cropName;
            if (file_put_contents($cropPath, $decoded) === false) {
                return ['success' => false, 'status' => 500, 'message' => 'Falha ao salvar recorte da logo.'];
            }
            @chmod($cropPath, 0644);

            $smallName    = 'logo_small_' . bin2hex(random_bytes(16)) . '.png';
            $smallPathAbs = $brandDir . DIRECTORY_SEPARATOR . $smallName;

            if (function_exists('imagecreatefromstring') && function_exists('imagescale')) {
                $im = @imagecreatefromstring($decoded);
                if ($im !== false) {
                    $w       = imagesx($im);
                    $h       = imagesy($im);
                    $targetW = 220;
                    $targetH = max(60, (int) round(($h / max(1, $w)) * $targetW));
                    $scaled  = imagescale($im, $targetW, $targetH);
                    if ($scaled !== false) {
                        imagepng($scaled, $smallPathAbs, 9);
                        @chmod($smallPathAbs, 0644);
                        imagedestroy($scaled);
                    } else {
                        file_put_contents($smallPathAbs, $decoded);
                        @chmod($smallPathAbs, 0644);
                    }
                    imagedestroy($im);
                } else {
                    file_put_contents($smallPathAbs, $decoded);
                    @chmod($smallPathAbs, 0644);
                }
            } else {
                file_put_contents($smallPathAbs, $decoded);
                @chmod($smallPathAbs, 0644);
            }
        }

        $originalPath = $this->relativePublicPath(self::BRANDING_DIR, $originalName);
        $cropPath     = $cropName  ? $this->relativePublicPath(self::BRANDING_DIR, $cropName)  : null;
        $smallPath    = $smallName ? $this->relativePublicPath(self::BRANDING_DIR, $smallName) : null;

        // The "display" path — prefer crop/small variant, fall back to original
        $displayPath = $smallPath ?? $cropPath ?? $originalPath;

        // Persist all variants
        $this->settingModel->setSetting('company_logo_original', $originalPath, 'string');
        $this->settingModel->setSetting('company_logo_crop',     $cropPath    ?? $originalPath, 'string');
        $this->settingModel->setSetting('company_logo_small',    $smallPath   ?? $originalPath, 'string');
        $this->settingModel->setSetting('company_logo',          $displayPath, 'string');
        // Keep legacy key in sync so older code paths still work
        $this->settingModel->setSetting('logo_path',             $displayPath, 'string');

        // Sync design_system JSON
        $this->syncDesignSystemLogo($displayPath);

        $this->settingModel->clearCache();

        return [
            'success' => true,
            'message' => 'Logo enviada e processada com sucesso.',
            'data'    => [
                'original' => $originalPath,
                'crop'     => $cropPath,
                'small'    => $smallPath,
                'url'      => base_url($displayPath),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function syncDesignSystemLogo(string $relativePath): void
    {
        $this->syncDesignSystemField('logo', base_url($relativePath));
    }

    private function syncDesignSystemFavicon(string $relativePath): void
    {
        $this->syncDesignSystemField('favicon', base_url($relativePath));
    }

    private function syncDesignSystemField(string $field, string $url): void
    {
        try {
            $raw = (string) $this->settingModel->get('design_system', '');
            if (empty($raw)) {
                return;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return;
            }
            if (!isset($data['custom']) || !is_array($data['custom'])) {
                $data['custom'] = [];
            }
            $data['custom'][$field] = $url;
            $this->settingModel->setSetting('design_system', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json', 'general');
        } catch (\Throwable $e) {
            log_message('warning', '[SystemBrandingAssetService] syncDesignSystemField failed: ' . $e->getMessage());
        }
    }

    private function validateAsset(mixed $file, array $validExtensions, array $validMimes, int $maxSize, string $label, string $dir, string $prefix, array &$errors, array &$filesToProcess, string $settingKey): void
    {
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->hasMoved()) {
            return;
        }

        $ext = strtolower((string) $file->getExtension());
        if (!in_array($ext, $validExtensions, true)) {
            $errors[] = "{$label}: tipo inválido";
            return;
        }

        $realMime = supportponto_detect_real_mime($file->getTempName());
        if ($realMime === null || !in_array($realMime, $validMimes, true)) {
            $errors[] = "{$label}: MIME inválido";
            return;
        }

        if ($file->getSize() > $maxSize) {
            $errors[] = "{$label}: muito grande";
            return;
        }

        $filesToProcess[$settingKey] = ['file' => $file, 'ext' => $ext, 'dir' => $dir, 'prefix' => $prefix, 'allowed_extensions' => $validExtensions, 'allowed_mimes' => $validMimes, 'max_size' => $maxSize];
    }

    private function publicDirectoryPath(string $subDirectory): string
    {
        return rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::PUBLIC_UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subDirectory;
    }

    private function relativePublicPath(string $subDirectory, string $filename): string
    {
        return trim(self::PUBLIC_UPLOAD_ROOT . '/' . trim($subDirectory, '/') . '/' . ltrim($filename, '/'), '/');
    }

    private function deletePublicAsset(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || !preg_match('#^uploads/#', $relativePath)) {
            return;
        }
        $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function deleteStoredLogoVariants(): void
    {
        foreach (['company_logo_original', 'company_logo_crop', 'company_logo_small', 'company_logo', 'logo_path'] as $key) {
            $this->deletePublicAsset((string) $this->settingModel->get($key, ''));
        }
    }
}

<?php

namespace App\Services\Admin;

use Config\Services;

use App\Libraries\DesignSystem;
use App\Models\SettingModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class AppearanceSettingsService
{
    private const PUBLIC_UPLOAD_ROOT = 'assets/uploads';
    private const FILE_RULES = [
        'logo' => [
            'folder' => 'logos',
            'setting_key' => 'logo_path',
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_bytes' => 2097152,
            'label' => 'Logo',
        ],
        'logo_auth' => [
            'folder' => 'logos',
            'setting_key' => 'logo_auth_path',
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_bytes' => 2097152,
            'label' => 'Logo (páginas sem login)',
        ],
        'logo_sidebar' => [
            'folder' => 'logos',
            'setting_key' => 'logo_sidebar_path',
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_bytes' => 2097152,
            'label' => 'Logo (menu lateral)',
        ],
        'favicon' => [
            'folder' => 'favicons',
            'setting_key' => 'favicon_path',
            'mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'],
            'extensions' => ['png', 'jpg', 'jpeg', 'webp', 'ico'],
            'max_bytes' => 1048576,
            'label' => 'Favicon',
        ],
        'login_background' => [
            'folder' => 'backgrounds',
            'setting_key' => 'login_background_path',
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_bytes' => 5242880,
            'label' => 'Imagem de fundo',
        ],
    ];

    public function __construct(
        private readonly ?SettingModel $settingModel = null,
        private readonly ?DesignSystem $designSystem = null,
    ) {
    }

    private function settings(): SettingModel
    {
        return $this->settingModel ?? Services::settings(false);
    }

    private function design(): DesignSystem
    {
        return $this->designSystem ?? new DesignSystem();
    }

    public function pageData(): array
    {
        return [
            'settings' => $this->settings()->getByGroupMap('appearance'),
            'currentConfig' => $this->design()->getAll(),
        ];
    }

    public function rules(): array
    {
        $hexRule = 'permit_empty|regex_match[/^#[0-9A-Fa-f]{6}$/]';
        return [
            'primary_color'   => $hexRule,
            'secondary_color' => $hexRule,
            'success_color'   => $hexRule,
            'warning_color'   => $hexRule,
            'danger_color'    => $hexRule,
            'info_color'      => $hexRule,
            'font_family'     => 'permit_empty|max_length[100]',
            'theme_mode'      => 'permit_empty|in_list[light,dark,auto]',
        ];
    }

    public function update(array $data, array $files, ?int $userId = null): array
    {
        helper('file_upload');

        try {
            $this->handleFileUploads($data, $files);

            $db = \Config\Database::connect();
            $db->transStart();

            if (!$this->settings()->setMultiple($data, 'appearance')) {
                throw new \RuntimeException('Failed to save appearance settings');
            }

            $this->design()->updateColors([
                'primary' => $data['primary_color'] ?? null,
                'secondary' => $data['secondary_color'] ?? null,
                'success' => $data['success_color'] ?? null,
                'warning' => $data['warning_color'] ?? null,
                'danger' => $data['danger_color'] ?? null,
                'info' => $data['info_color'] ?? null,
            ]);

            if (isset($data['font_family'])) {
                $this->design()->updateTypography(['font_family' => $data['font_family']]);
            }

            $this->design()->updateCustom([
                'company_name' => $data['company_name'] ?? null,
                'theme_mode' => $data['theme_mode'] ?? 'light',
            ]);

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed');
            }

            $this->clearCache();
            log_message('info', 'Appearance settings updated successfully', ['user' => $userId]);

            return ['success' => true, 'message' => 'Configurações de aparência atualizadas com sucesso'];
        } catch (\Throwable $e) {
            log_message('error', 'Error updating appearance settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            return ['success' => false, 'message' => 'Erro ao atualizar configurações. Por favor, tente novamente.'];
        }
    }

    public function uploadLogo(UploadedFile $file): array
    {
        $result = $this->uploadAsset($file, 'logo');
        if (!($result['success'] ?? false)) {
            return $result;
        }

        $this->design()->updateCustom(['logo' => base_url($result['path'])]);
        $colors = $this->extractColorsFromImage(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $result['path']));
        if ($colors) {
            $this->design()->updateColors($colors);
        }

        // Sync all logo variant keys so support_logo_url() always resolves to the new logo
        $path = $result['path'];
        $settings = $this->settings();
        foreach (['company_logo', 'company_logo_small', 'company_logo_crop', 'company_logo_original'] as $key) {
            $settings->setSetting($key, $path, 'string', 'appearance');
        }
        $settings->clearCache();

        return [
            'success' => true,
            'message' => 'Logo enviado com sucesso',
            'url' => base_url($result['path']),
            'colors' => $colors,
        ];
    }

    
    public function uploadFavicon(UploadedFile $file): array
    {
        $result = $this->uploadAsset($file, 'favicon');
        if (!($result['success'] ?? false)) {
            return $result;
        }

        $this->design()->updateCustom(['favicon' => base_url($result['path'])]);

        // Sync company_favicon so all resolution paths find the new favicon
        $this->settings()->setSetting('company_favicon', $result['path'], 'string', 'appearance');
        $this->settings()->clearCache();

        return [
            'success' => true,
            'message' => 'Favicon enviado com sucesso',
            'url' => base_url($result['path']),
        ];
    }

    public function uploadLoginBackground(UploadedFile $file): array
    {
        $result = $this->uploadAsset($file, 'login_background');
        if (!($result['success'] ?? false)) {
            return $result;
        }

        // Update design_system and sync login_background alias
        $this->design()->updateCustom(['login_background' => base_url($result['path'])]);
        $this->settings()->setSetting('login_background', $result['path'], 'string', 'appearance');
        $this->settings()->clearCache();

        return [
            'success' => true,
            'message' => 'Fundo de login enviado com sucesso',
            'url' => base_url($result['path']),
        ];
    }

    public function uploadLogoAuth(UploadedFile $file): array
    {
        $result = $this->uploadAsset($file, 'logo_auth');
        if (!($result['success'] ?? false)) {
            return $result;
        }
        $this->settings()->clearCache();

        return [
            'success' => true,
            'message' => 'Logo de autenticação enviada com sucesso',
            'url' => base_url($result['path']),
        ];
    }

    public function uploadLogoSidebar(UploadedFile $file): array
    {
        $result = $this->uploadAsset($file, 'logo_sidebar');
        if (!($result['success'] ?? false)) {
            return $result;
        }
        $this->settings()->clearCache();

        return [
            'success' => true,
            'message' => 'Logo do menu lateral enviada com sucesso',
            'url' => base_url($result['path']),
        ];
    }

    public function reset(): array
    {
        try {
            $this->settings()->deleteGroup('appearance');
            $this->design()->resetToDefaults();
            $this->clearCache();

            return ['success' => true, 'message' => 'Aparência resetada para o padrão'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao resetar: ' . $e->getMessage()];
        }
    }

    public function preview(array $colors): array
    {
        $tempDesign = $this->design();
        if (!empty($colors)) {
            $tempDesign->updateColors($colors);
        }

        return ['success' => true, 'css' => $tempDesign->generateCSS()];
    }

    private function clearCache(): void
    {
        cache()->delete('design_system_css');
        cache()->delete('design_system');
        $this->design()->invalidateCache();
    }

    private function handleFileUploads(array &$data, array $files): void
    {
        foreach (array_keys(self::FILE_RULES) as $field) {
            $file = $files[$field] ?? null;
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $result = $this->uploadAsset($file, $field);
            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['message'] ?? 'Falha ao enviar arquivo.');
            }

            $rule = self::FILE_RULES[$field];
            $data[$rule['setting_key']] = $result['path'];
        }
    }

    private function uploadAsset(UploadedFile $file, string $field): array
    {
        $rule = self::FILE_RULES[$field] ?? null;
        if ($rule === null) {
            return ['success' => false, 'message' => 'Configuração de upload inválida.'];
        }

        if (!$file->isValid() || $file->hasMoved()) {
            return ['success' => false, 'message' => 'Arquivo inválido'];
        }

        $ext = strtolower((string) $file->getExtension());
        if (!in_array($ext, $rule['extensions'], true)) {
            return ['success' => false, 'message' => $rule['label'] . ': extensão não permitida.'];
        }

        helper('file_upload');
        $realMime = supportponto_detect_real_mime($file->getTempName());
        if ($realMime === null || !in_array($realMime, $rule['mimes'], true)) {
            return ['success' => false, 'message' => $rule['label'] . ': tipo de arquivo não permitido.'];
        }

        if ($file->getSize() > $rule['max_bytes']) {
            return ['success' => false, 'message' => $rule['label'] . ': arquivo muito grande.'];
        }

        $dir = $this->publicDirectoryPath($rule['folder']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Não foi possível preparar o diretório de upload.'];
        }

        $oldPath = (string) $this->settings()->get($rule['setting_key'], '');
        $this->deletePublicAsset($oldPath);

        helper('file_upload');

        $stored = supportponto_store_public_image_asset(
            $file,
            $dir,
            $field,
            $rule['extensions'],
            $rule['mimes'],
            $rule['max_bytes']
        );

        if (($stored['success'] ?? false) !== true) {
            return ['success' => false, 'message' => $rule['label'] . ': ' . ($stored['message'] ?? 'falha no upload seguro.')];
        }

        $path = trim(self::PUBLIC_UPLOAD_ROOT . '/' . $rule['folder'] . '/' . $stored['filename'], '/');
        $this->settings()->setSetting($rule['setting_key'], $path, 'file', 'appearance');

        return ['success' => true, 'path' => $path];
    }

    private function publicDirectoryPath(string $folder): string
    {
        return rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PUBLIC_UPLOAD_ROOT) . DIRECTORY_SEPARATOR . $folder;
    }

    private function deletePublicAsset(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || preg_match('#^(https?:)?//#i', $relativePath)) {
            return;
        }

        $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function extractColorsFromImage(string $imagePath): ?array
    {
        try {
            return null;
        } catch (\Throwable $e) {
            log_message('error', 'Error extracting colors: ' . $e->getMessage());
            return null;
        }
    }
    /**
     * Salva imagem recebida como base64 (vinda do modal de corte).
     * Para logo: salva PNG 512×512 com fundo transparente (contain).
     * Para favicon: salva PNG principal + 4 tamanhos (16,32,48,180).
     */
    public function saveCroppedImage(string $type, string $base64Data): array
    {
        if (!in_array($type, ['logo', 'logo_auth', 'logo_sidebar', 'favicon'], true)) {
            return ['success' => false, 'message' => 'Tipo inválido.'];
        }

        // ── 1. Decodificar base64 ──────────────────────────────
        $raw = preg_replace('#^data:image/[a-z]+;base64,#i', '', $base64Data);
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 64) {
            return ['success' => false, 'message' => 'Dados de imagem inválidos.'];
        }
        if (strlen($binary) > 8388608) {   // 8 MB hard cap
            return ['success' => false, 'message' => 'Imagem muito grande (máx 8 MB).'];
        }

        // ── 2. Criar resource GD ──────────────────────────────
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return ['success' => false, 'message' => 'Não foi possível processar a imagem.'];
        }

        try {
            if ($type === 'logo') {
                return $this->saveLogo($src);
            }
            if ($type === 'logo_auth') {
                return $this->saveLogoAuth($src);
            }
            if ($type === 'logo_sidebar') {
                return $this->saveLogoSidebar($src);
            }
            return $this->saveFavicon($src);
        } finally {
            imagedestroy($src);
        }
    }

    private function saveLogo(\GdImage $src): array
    {
        $rule  = self::FILE_RULES['logo'];
        $dir   = $this->publicDirectoryPath($rule['folder']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Diretório de logos inacessível.'];
        }

        // Remover logo antiga
        $oldPath = (string) $this->settings()->get($rule['setting_key'], '');
        $this->deletePublicAsset($oldPath);

        // Canvas 512×512 transparente (contain)
        $size   = 512;
        $sw     = imagesx($src);
        $sh     = imagesy($src);
        $scale  = min($size / $sw, $size / $sh);
        $dw     = (int) round($sw * $scale);
        $dh     = (int) round($sh * $scale);
        $dx     = (int) (($size - $dw) / 2);
        $dy     = (int) (($size - $dh) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

        $filename = date('Ymd_His') . '_logo_' . bin2hex(random_bytes(4)) . '.png';
        $absPath  = $dir . DIRECTORY_SEPARATOR . $filename;

        ob_start();
        imagepng($canvas, null, 9);
        $pngData = ob_get_clean();
        imagedestroy($canvas);

        if (file_put_contents($absPath, $pngData) === false) {
            return ['success' => false, 'message' => 'Falha ao salvar arquivo de logo.'];
        }
        chmod($absPath, 0644);

        $relPath = self::PUBLIC_UPLOAD_ROOT . '/' . $rule['folder'] . '/' . $filename;
        $this->settings()->setSetting($rule['setting_key'], $relPath, 'file', 'appearance');
        foreach (['company_logo', 'company_logo_small', 'company_logo_crop', 'company_logo_original'] as $k) {
            $this->settings()->setSetting($k, $relPath, 'string', 'appearance');
        }
        $this->design()->updateCustom(['logo' => base_url($relPath)]);
        $this->settings()->clearCache();
        $this->clearCache();

        return [
            'success' => true,
            'message' => 'Logo enviada e processada com sucesso.',
            'url'     => base_url($relPath) . '?v=' . time(),
        ];
    }

    private function saveLogoAuth(\GdImage $src): array
    {
        $rule = self::FILE_RULES['logo_auth'];
        $dir  = $this->publicDirectoryPath($rule['folder']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Diretório de logos inacessível.'];
        }

        $oldPath = (string) $this->settings()->get($rule['setting_key'], '');
        $this->deletePublicAsset($oldPath);

        // Canvas 512×512 transparente (contain) — exibido a 64px nas páginas de auth
        $size = 512;
        $sw = imagesx($src); $sh = imagesy($src);
        $scale = min($size / $sw, $size / $sh);
        $dw = (int) round($sw * $scale); $dh = (int) round($sh * $scale);
        $dx = (int) (($size - $dw) / 2); $dy = (int) (($size - $dh) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false); imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

        $filename = date('Ymd_His') . '_logo_auth_' . bin2hex(random_bytes(4)) . '.png';
        $absPath  = $dir . DIRECTORY_SEPARATOR . $filename;
        ob_start(); imagepng($canvas, null, 9); $pngData = ob_get_clean();
        imagedestroy($canvas);

        if (file_put_contents($absPath, $pngData) === false) {
            return ['success' => false, 'message' => 'Falha ao salvar logo de autenticação.'];
        }
        chmod($absPath, 0644);

        $relPath = self::PUBLIC_UPLOAD_ROOT . '/' . $rule['folder'] . '/' . $filename;
        $this->settings()->setSetting($rule['setting_key'], $relPath, 'file', 'appearance');
        $this->settings()->clearCache();
        $this->clearCache();

        return ['success' => true, 'message' => 'Logo de autenticação processada com sucesso.', 'url' => base_url($relPath) . '?v=' . time()];
    }

    private function saveLogoSidebar(\GdImage $src): array
    {
        $rule = self::FILE_RULES['logo_sidebar'];
        $dir  = $this->publicDirectoryPath($rule['folder']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Diretório de logos inacessível.'];
        }

        $oldPath = (string) $this->settings()->get($rule['setting_key'], '');
        $this->deletePublicAsset($oldPath);

        // Canvas 512×128 transparente (contain) — exibido até 160×48px no sidebar
        $cw = 512; $ch = 128;
        $sw = imagesx($src); $sh = imagesy($src);
        $scale = min($cw / $sw, $ch / $sh);
        $dw = (int) round($sw * $scale); $dh = (int) round($sh * $scale);
        $dx = (int) (($cw - $dw) / 2); $dy = (int) (($ch - $dh) / 2);

        $canvas = imagecreatetruecolor($cw, $ch);
        imagealphablending($canvas, false); imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $cw, $ch, $transparent);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

        $filename = date('Ymd_His') . '_logo_sidebar_' . bin2hex(random_bytes(4)) . '.png';
        $absPath  = $dir . DIRECTORY_SEPARATOR . $filename;
        ob_start(); imagepng($canvas, null, 9); $pngData = ob_get_clean();
        imagedestroy($canvas);

        if (file_put_contents($absPath, $pngData) === false) {
            return ['success' => false, 'message' => 'Falha ao salvar logo do menu lateral.'];
        }
        chmod($absPath, 0644);

        $relPath = self::PUBLIC_UPLOAD_ROOT . '/' . $rule['folder'] . '/' . $filename;
        $this->settings()->setSetting($rule['setting_key'], $relPath, 'file', 'appearance');
        $this->settings()->clearCache();
        $this->clearCache();

        return ['success' => true, 'message' => 'Logo do menu lateral processada com sucesso.', 'url' => base_url($relPath) . '?v=' . time()];
    }

    private function saveFavicon(\GdImage $src): array
    {
        $rule = self::FILE_RULES['favicon'];
        $dir  = $this->publicDirectoryPath($rule['folder']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Diretório de favicons inacessível.'];
        }

        // Remover favicon antigo
        $oldPath = (string) $this->settings()->get($rule['setting_key'], '');
        $this->deletePublicAsset($oldPath);

        $baseName = date('Ymd_His') . '_fav_' . bin2hex(random_bytes(4));
        $sizes    = [16, 32, 48, 180];
        $paths    = [];

        foreach ($sizes as $px) {
            $canvas = imagecreatetruecolor($px, $px);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $px, $px, $transparent);
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $px, $px,
                imagesx($src), imagesy($src));

            $file = $baseName . '_' . $px . '.png';
            ob_start();
            imagepng($canvas, null, 9);
            $pngData = ob_get_clean();
            imagedestroy($canvas);

            file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $pngData);
            chmod($dir . DIRECTORY_SEPARATOR . $file, 0644);
            $paths[$px] = self::PUBLIC_UPLOAD_ROOT . '/' . $rule['folder'] . '/' . $file;
        }

        // O principal favicon é o 32×32
        $mainPath = $paths[32];
        $this->settings()->setSetting($rule['setting_key'], $mainPath, 'file', 'appearance');
        $this->settings()->setSetting('company_favicon', $mainPath, 'string', 'appearance');
        // Guarda caminhos por tamanho para pwa-meta.php
        $this->settings()->setSetting('favicon_path_16',  $paths[16],  'string', 'appearance');
        $this->settings()->setSetting('favicon_path_32',  $paths[32],  'string', 'appearance');
        $this->settings()->setSetting('favicon_path_48',  $paths[48],  'string', 'appearance');
        $this->settings()->setSetting('favicon_path_180', $paths[180], 'string', 'appearance');
        $this->design()->updateCustom(['favicon' => base_url($mainPath)]);
        $this->settings()->clearCache();
        $this->clearCache();

        return [
            'success' => true,
            'message' => 'Favicon enviado e processado com sucesso (4 tamanhos gerados).',
            'url'     => base_url($mainPath) . '?v=' . time(),
            'sizes'   => array_map(fn($p) => base_url($p), $paths),
        ];
    }

}

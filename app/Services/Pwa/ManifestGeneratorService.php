<?php

declare(strict_types=1);

namespace App\Services\Pwa;

use App\Models\SettingModel;

/**
 * Gera e persiste o manifest.webmanifest a partir das configurações do banco.
 *
 * Deve ser chamado após qualquer alteração nas configurações PWA ou ao fazer
 * upload de um ícone, garantindo que o arquivo estático servido pelo nginx
 * reflita sempre o estado atual das configurações.
 */
class ManifestGeneratorService
{
    private SettingModel $settings;
    private string $manifestPath;

    public function __construct(?SettingModel $settings = null)
    {
        $this->settings     = $settings ?? model(SettingModel::class);
        $this->manifestPath = FCPATH . 'manifest.webmanifest';
    }

    public function regenerate(): bool
    {
        try {
            $manifest = $this->build();
            $json     = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return false;
            }

            $result = @file_put_contents($this->manifestPath, $json, LOCK_EX);
            if ($result === false) {
                log_message('warning', '[ManifestGenerator] Could not write manifest.webmanifest — check permissions on public/');
                return false;
            }

            @chmod($this->manifestPath, 0644);
            return true;
        } catch (\Throwable $e) {
            log_message('error', '[ManifestGenerator] ' . $e->getMessage());
            return false;
        }
    }

    public function build(): array
    {
        $pwa = $this->settings->getByGroupMap('pwa') ?? [];

        $name        = (string) ($pwa['pwa_app_name']         ?? 'SupportPONTO');
        $shortName   = (string) ($pwa['pwa_short_name']       ?? 'PONTO');
        $description = (string) ($pwa['pwa_description']      ?? 'Sistema de Ponto Eletrônico');
        $startUrl    = $this->normalizeStartUrl((string) ($pwa['pwa_start_url'] ?? '/'));
        $display     = (string) ($pwa['pwa_display']          ?? 'standalone');
        $orientation = (string) ($pwa['pwa_orientation']      ?? 'any');
        $bgColor     = (string) ($pwa['pwa_background_color'] ?? '#ffffff');
        $themeColor  = (string) ($pwa['pwa_theme_color']      ?? '#4fa14f');
        $iconPath    = (string) ($pwa['pwa_icon']             ?? '');
        $shortcutIcon= (string) ($pwa['pwa_shortcut_icon']    ?? $iconPath);

        $icons = $this->buildIconList($iconPath);

        return [
            'name'             => $name,
            'short_name'       => $shortName,
            'description'      => $description,
            'start_url'        => $startUrl,
            'scope'            => '/',
            'display'          => $display,
            'orientation'      => $orientation,
            'background_color' => $bgColor,
            'theme_color'      => $themeColor,
            'lang'             => 'pt-BR',
            'dir'              => 'ltr',
            'categories'       => ['business', 'productivity', 'utilities'],
            'icons'            => $icons,
            'shortcuts'        => $this->buildShortcuts($shortcutIcon),
            'screenshots'      => [],
            'related_applications'      => [],
            'prefer_related_applications' => false,
        ];
    }

    private function buildIconList(string $customIconPath): array
    {
        $staticBase = '/assets/img';
        $icons      = [];

        // Small static icons (keep the pre-existing generated set)
        foreach ([72, 96, 128, 144, 152] as $size) {
            $icons[] = [
                'src'     => "{$staticBase}/icon-{$size}.png",
                'sizes'   => "{$size}x{$size}",
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }

        // For 192 and 512 prefer the custom uploaded icon
        $customUrl = $this->resolveCustomIconUrl($customIconPath);

        if ($customUrl !== null) {
            // Custom icon covers 192 and 512 (browser scales as needed)
            $icons[] = ['src' => $customUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => $customUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'];
            $icons[] = ['src' => $customUrl, 'sizes' => '384x384', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => $customUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => $customUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        } else {
            // Fallback to static generated icons
            foreach ([192, 384, 512] as $size) {
                $icons[] = ['src' => "{$staticBase}/icon-{$size}.png", 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
            }
            $icons[] = ['src' => "{$staticBase}/icon-192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'];
            $icons[] = ['src' => "{$staticBase}/icon-512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return $icons;
    }

    private function buildShortcuts(string $shortcutIconPath): array
    {
        $iconUrl = $this->resolveCustomIconUrl($shortcutIconPath) ?? '/assets/img/icon-192.png';

        return [
            [
                'name'        => 'Registrar Ponto',
                'short_name'  => 'Registro',
                'description' => 'Registrar ponto rapidamente',
                'url'         => '/registro-rapido',
                'icons'       => [['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png']],
            ],
            [
                'name'        => 'Ver Registros',
                'short_name'  => 'Histórico',
                'description' => 'Visualizar histórico de registros',
                'url'         => '/registro',
                'icons'       => [['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png']],
            ],
        ];
    }

    private function normalizeStartUrl(string $url): string
    {
        // Convert full URL to path-only so scope "/" always matches
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($url);
            $path   = ($parsed['path'] ?? '/') ?: '/';
            if (isset($parsed['query'])) {
                $path .= '?' . $parsed['query'];
            }
            return $path;
        }

        return $url ?: '/';
    }

    private function resolveCustomIconUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $abs = FCPATH . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (! is_file($abs)) {
            return null;
        }

        // Return root-relative URL (no domain, works for any environment)
        return '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');
    }
}

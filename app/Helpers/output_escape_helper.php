<?php

declare(strict_types=1);

if (! function_exists('sp_attr')) {
    function sp_attr($value): string
    {
        return esc((string) ($value ?? ''), 'attr');
    }
}

if (! function_exists('sp_js')) {
    function sp_js($value): string
    {
        return esc((string) ($value ?? ''), 'js');
    }
}

if (! function_exists('sp_safe_url')) {
    function sp_safe_url($value, string $fallback = '#'): string
    {
        $url = trim((string) ($value ?? ''));
        if ($url === '') {
            return esc($fallback, 'attr');
        }

        if (preg_match('~^(?:/|\./|\.\./|\?|#)~', $url) === 1) {
            return esc($url, 'attr');
        }

        if (preg_match('#^data:image/(?:png|jpeg|jpg|gif|webp|svg\+xml);base64,[a-zA-Z0-9+/=\r\n]+$#', $url) === 1) {
            return esc($url, 'attr');
        }

        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            return esc($fallback, 'attr');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme === '') {
            return esc($url, 'attr');
        }

        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return esc($url, 'html');
        }

        return esc($fallback, 'attr');
    }
}

if (! function_exists('sp_style_color')) {
    function sp_style_color($value, string $fallback = '#6c757d'): string
    {
        $color = trim((string) ($value ?? ''));
        if ($color === '') {
            return esc($fallback, 'attr');
        }

        $patterns = [
            '/^#[0-9a-fA-F]{3,8}$/',
            '/^rgba?\((?:[^()]*)\)$/',
            '/^hsla?\((?:[^()]*)\)$/',
            '/^var\(--[a-zA-Z0-9_-]+\)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $color) === 1) {
                return esc($color, 'attr');
            }
        }

        return esc($fallback, 'attr');
    }
}


if (! function_exists('sp_text')) {
    function sp_text($value): string
    {
        return esc((string) ($value ?? ''));
    }
}

if (! function_exists('sp_flash')) {
    function sp_flash($value): string
    {
        helper('security');
        return esc(security_sanitize_flash($value));
    }
}

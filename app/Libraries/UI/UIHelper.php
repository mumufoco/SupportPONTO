<?php

declare(strict_types=1);

namespace App\Libraries\UI;

/**
 * UI Helper
 *
 * Helper functions for common UI tasks
 */
class UIHelper
{
    /**
     * Format file size
     */
    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format number with locale
     */
    public static function formatNumber($number, int $decimals = 0): string
    {
        return number_format($number, $decimals, ',', '.');
    }

    /**
     * Format currency (BRL)
     */
    public static function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Format date
     */
    public static function formatDate(string $date, string $format = 'd/m/Y'): string
    {
        try {
            $dt = new \DateTime($date);
            return $dt->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format datetime
     */
    public static function formatDateTime(string $datetime, string $format = 'd/m/Y H:i'): string
    {
        return self::formatDate($datetime, $format);
    }

    /**
     * Format time ago (relative time)
     */
    public static function timeAgo(string $datetime): string
    {
        try {
            $dt = new \DateTime($datetime);
            $now = new \DateTime();
            $diff = $now->diff($dt);

            if ($diff->y > 0) {
                return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
            }
            if ($diff->m > 0) {
                return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
            }
            if ($diff->d > 0) {
                return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
            }
            if ($diff->h > 0) {
                return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
            }
            if ($diff->i > 0) {
                return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
            }

            return 'Agora mesmo';
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * Truncate text
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * Get initials from name
     */
    public static function getInitials(string $name, int $max = 2): string
    {
        $words = explode(' ', trim($name));
        $initials = '';

        foreach (array_slice($words, 0, $max) as $word) {
            if (!empty($word)) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }

        return $initials;
    }

    /**
     * Get avatar color based on string
     */
    public static function getAvatarColor(string $string): string
    {
        $colors = [
            '#3B82F6', // blue
            '#8B5CF6', // purple
            '#10B981', // green
            '#F59E0B', // yellow
            '#EF4444', // red
            '#06B6D4', // cyan
            '#EC4899', // pink
            '#F97316', // orange
        ];

        $hash = crc32($string);
        return $colors[$hash % count($colors)];
    }

    /**
     * Generate avatar HTML
     */
    public static function avatar(string $name, ?string $image = null, string $size = '40px'): string
    {
        $html = '<div class="user-avatar" style="width: ' . $size . '; height: ' . $size . ';';

        if ($image) {
            $html .= ' background-image: url(\'' . esc($image) . '\'); background-size: cover; background-position: center;">';
        } else {
            $color = self::getAvatarColor($name);
            $initials = self::getInitials($name);
            $html .= ' background-color: ' . $color . ';">';
            $html .= '<span style="color: white; font-weight: 600;">' . esc($initials) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate status badge
     */
    public static function statusBadge(string $status): string
    {
        $styles = [
            'active' => ['text' => 'Ativo', 'style' => 'success'],
            'inactive' => ['text' => 'Inativo', 'style' => 'danger'],
            'pending' => ['text' => 'Pendente', 'style' => 'warning'],
            'approved' => ['text' => 'Aprovado', 'style' => 'success'],
            'rejected' => ['text' => 'Rejeitado', 'style' => 'danger'],
            'draft' => ['text' => 'Rascunho', 'style' => 'secondary'],
        ];

        $config = $styles[strtolower($status)] ?? ['text' => $status, 'style' => 'primary'];

        return ComponentBuilder::badge([
            'text' => $config['text'],
            'style' => $config['style']
        ]);
    }

    /**
     * Generate confirmation button with JS confirm
     */
    public static function confirmButton(string $text, string $url, string $message = 'Tem certeza?', string $style = 'danger'): string
    {
        $onclick = "return confirm('" . esc($message) . "')";

        return ComponentBuilder::button([
            'text' => $text,
            'url' => $url,
            'style' => $style,
            'onclick' => $onclick
        ]);
    }

    /**
     * Generate icon
     */
    public static function icon(string $name, ?string $class = null): string
    {
        $html = '<i class="fas fa-' . esc($name);
        if ($class) {
            $html .= ' ' . esc($class);
        }
        $html .= '"></i>';

        return $html;
    }

    /**
     * Generate loading spinner
     */
    public static function spinner(string $size = ''): string
    {
        $class = 'spinner';
        if ($size) {
            $class .= ' spinner-' . $size;
        }

        return '<div class="' . $class . '"></div>';
    }

    /**
     * Generate empty state
     */
    public static function emptyState(string $message = 'Nenhum registro encontrado', ?string $icon = 'inbox'): string
    {
        $html = '<div class="sp-ui-empty-state">';

        if ($icon) {
            $html .= '<i class="fas fa-' . esc($icon) . ' sp-ui-empty-state__icon"></i>';
        }

        $html .= '<p>' . esc($message) . '</p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate grid layout helper
     */
    public static function grid(array $items, int $columns = 3, string $gap = 'lg'): string
    {
        $allowedGaps = ['xs', 'sm', 'md', 'lg', 'xl'];
        $safeGap = in_array($gap, $allowedGaps, true) ? $gap : 'lg';
        $html = '<div class="sp-ui-grid sp-ui-gap-' . esc($safeGap) . '">';

        foreach ($items as $item) {
            $html .= $item;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate flex layout helper
     */
    public static function flex(array $items, string $justify = 'start', string $align = 'center', string $gap = 'md'): string
    {
        $justifyMap = [
            'start' => 'flex-start',
            'flex-start' => 'flex-start',
            'center' => 'center',
            'end' => 'flex-end',
            'flex-end' => 'flex-end',
            'between' => 'space-between',
            'space-between' => 'space-between',
            'around' => 'space-around',
            'space-around' => 'space-around',
            'evenly' => 'space-evenly',
            'space-evenly' => 'space-evenly',
        ];
        $alignMap = [
            'start' => 'flex-start',
            'flex-start' => 'flex-start',
            'center' => 'center',
            'end' => 'flex-end',
            'flex-end' => 'flex-end',
            'stretch' => 'stretch',
            'baseline' => 'baseline',
        ];
        $allowedGaps = ['xs', 'sm', 'md', 'lg', 'xl'];
        $safeGap = in_array($gap, $allowedGaps, true) ? $gap : 'md';
        $safeJustify = $justifyMap[$justify] ?? 'flex-start';
        $safeAlign = $alignMap[$align] ?? 'center';
        $html = '<div class="sp-ui-flex sp-ui-gap-' . esc($safeGap) . '" style="--sp-flex-justify: ' . esc($safeJustify) . '; --sp-flex-align: ' . esc($safeAlign) . ';">';

        foreach ($items as $item) {
            $html .= $item;
        }

        $html .= '</div>';

        return $html;
    }
}

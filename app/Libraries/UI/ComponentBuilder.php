<?php

declare(strict_types=1);

namespace App\Libraries\UI;

/**
 * Component Builder
 *
 * Utility class for building reusable UI components
 */
class ComponentBuilder
{
    /**
     * Build a card component
     *
     * @param array $options Card options
     * @return string HTML
     */
    public static function card(array $options = []): string
    {
        $title = $options['title'] ?? '';
        $content = $options['content'] ?? '';
        $footer = $options['footer'] ?? '';
        $actions = $options['actions'] ?? '';
        $icon = $options['icon'] ?? '';
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? '';
        $attributes = $options['attributes'] ?? '';

        $html = '<div class="card ' . esc($class) . '"';
        if ($id) $html .= ' id="' . esc($id) . '"';
        if ($attributes) $html .= ' ' . $attributes;
        $html .= '>';

        // Card Header
        if ($title || $actions) {
            $html .= '<div class="card-header">';
            if ($title) {
                $html .= '<h3 class="card-title">';
                if ($icon) {
                    $html .= '<i class="fas ' . esc($icon) . '"></i> ';
                }
                $html .= esc($title);
                $html .= '</h3>';
            }
            if ($actions) {
                $html .= '<div class="card-actions">' . $actions . '</div>';
            }
            $html .= '</div>';
        }

        // Card Body
        if ($content) {
            $html .= '<div class="card-body">' . $content . '</div>';
        }

        // Card Footer
        if ($footer) {
            $html .= '<div class="card-footer">' . $footer . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build a stat card component
     *
     * @param array $options Stat card options
     * @return string HTML
     */
    public static function statCard(array $options = []): string
    {
        $value = $options['value'] ?? '0';
        $label = $options['label'] ?? '';
        $icon = $options['icon'] ?? 'fa-chart-line';
        $color = $options['color'] ?? 'primary';
        $trend = $options['trend'] ?? null;
        $url = $options['url'] ?? '';

        $tag = $url ? 'a' : 'div';
        $href = $url ? ' href="' . esc($url) . '"' : '';
        $style = $url ? ' style="text-decoration: none; color: inherit;"' : '';

        $html = "<{$tag} class=\"stat-card\"{$href}{$style}>";
        $html .= '<div class="stat-icon bg-' . esc($color) . '">';
        $html .= '<i class="fas ' . esc($icon) . '"></i>';
        $html .= '</div>';
        $html .= '<div class="stat-content">';
        $html .= '<div class="stat-value">' . esc($value) . '</div>';
        $html .= '<div class="stat-label">' . esc($label) . '</div>';
        $html .= '</div>';

        if ($trend) {
            $trendClass = $trend['direction'] === 'up' ? 'trend-up' : 'trend-down';
            $html .= '<div class="stat-trend">';
            $html .= '<span class="' . $trendClass . '">';
            $html .= '<i class="fas fa-arrow-' . esc($trend['direction']) . '"></i> ';
            $html .= esc($trend['value']);
            $html .= '</span>';
            $html .= '</div>';
        }

        $html .= "</{$tag}>";

        return $html;
    }

    /**
     * Build a button component
     *
     * @param array $options Button options
     * @return string HTML
     */
    public static function button(array $options = []): string
    {
        $text = $options['text'] ?? 'Button';
        $icon = $options['icon'] ?? '';
        $type = $options['type'] ?? 'button';
        $style = $options['style'] ?? 'primary';
        $size = $options['size'] ?? '';
        $url = $options['url'] ?? '';
        $onclick = $options['onclick'] ?? '';
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? '';
        $disabled = $options['disabled'] ?? false;
        $attributes = $options['attributes'] ?? '';

        $classes = ['btn', 'btn-' . $style];
        if ($size) $classes[] = 'btn-' . $size;
        if ($class) $classes[] = $class;

        if ($url) {
            $html = '<a href="' . esc($url) . '" class="' . implode(' ', $classes) . '"';
            if ($id) $html .= ' id="' . esc($id) . '"';
            if ($attributes) $html .= ' ' . $attributes;
            $html .= '>';
        } else {
            $html = '<button type="' . esc($type) . '" class="' . implode(' ', $classes) . '"';
            if ($id) $html .= ' id="' . esc($id) . '"';
            if ($onclick) $html .= ' onclick="' . esc($onclick) . '"';
            if ($disabled) $html .= ' disabled';
            if ($attributes) $html .= ' ' . $attributes;
            $html .= '>';
        }

        if ($icon) {
            $html .= '<i class="fas ' . esc($icon) . '"></i> ';
        }
        $html .= esc($text);

        $html .= $url ? '</a>' : '</button>';

        return $html;
    }

    /**
     * Build a badge component
     *
     * @param array $options Badge options
     * @return string HTML
     */
    public static function badge(array $options = []): string
    {
        $text = $options['text'] ?? '';
        $style = $options['style'] ?? 'primary';
        $class = $options['class'] ?? '';
        $icon = $options['icon'] ?? '';

        $html = '<span class="badge badge-' . esc($style);
        if ($class) $html .= ' ' . esc($class);
        $html .= '">';

        if ($icon) {
            $html .= '<i class="fas ' . esc($icon) . '"></i> ';
        }
        $html .= esc($text);
        $html .= '</span>';

        return $html;
    }

    /**
     * Build an alert component
     *
     * @param array $options Alert options
     * @return string HTML
     */
    public static function alert(array $options = []): string
    {
        $message = $options['message'] ?? '';
        $type = $options['type'] ?? 'info';
        $dismissible = $options['dismissible'] ?? true;
        $icon = $options['icon'] ?? self::getAlertIcon($type);
        $class = $options['class'] ?? '';

        $html = '<div class="alert alert-' . esc($type);
        if ($dismissible) $html .= ' alert-dismissible';
        if ($class) $html .= ' ' . esc($class);
        $html .= '">';

        $html .= '<i class="fas ' . esc($icon) . '"></i>';
        $html .= '<span>' . $message . '</span>';

        if ($dismissible) {
            $html .= '<button type="button" class="alert-close" data-dismiss="alert">';
            $html .= '<i class="fas fa-times"></i>';
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build a table component
     *
     * @param array $options Table options
     * @return string HTML
     */
    public static function table(array $options = []): string
    {
        $columns = $options['columns'] ?? [];
        $data = $options['data'] ?? [];
        $class = $options['class'] ?? '';
        $responsive = $options['responsive'] ?? true;

        $html = '';
        if ($responsive) {
            $html .= '<div class="table-responsive">';
        }

        $html .= '<table class="table table-modern';
        if ($class) $html .= ' ' . esc($class);
        $html .= '">';

        // Header
        $html .= '<thead><tr>';
        foreach ($columns as $column) {
            $colClass = $column['class'] ?? '';
            $html .= '<th';
            if ($colClass) $html .= ' class="' . esc($colClass) . '"';
            $html .= '>' . esc($column['label'] ?? '') . '</th>';
        }
        $html .= '</tr></thead>';

        // Body
        $html .= '<tbody>';
        if (empty($data)) {
            $html .= '<tr><td colspan="' . count($columns) . '" class="sp-ui-table-empty">';
            $html .= '<i class="fas fa-inbox sp-ui-table-empty__icon"></i>';
            $html .= 'Nenhum registro encontrado';
            $html .= '</td></tr>';
        } else {
            foreach ($data as $row) {
                $html .= '<tr>';
                foreach ($columns as $column) {
                    $key = $column['key'] ?? '';
                    $colClass = $column['class'] ?? '';
                    $formatter = $column['formatter'] ?? null;

                    $value = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                    if ($formatter && is_callable($formatter)) {
                        $value = $formatter($value, $row);
                    }

                    $html .= '<td';
                    if ($colClass) $html .= ' class="' . esc($colClass) . '"';
                    $html .= '>' . $value . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';

        $html .= '</table>';

        if ($responsive) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Build a modal component
     *
     * @param array $options Modal options
     * @return string HTML
     */
    public static function modal(array $options = []): string
    {
        $id = $options['id'] ?? 'modal';
        $title = $options['title'] ?? '';
        $content = $options['content'] ?? '';
        $footer = $options['footer'] ?? '';
        $size = $options['size'] ?? '';

        $html = '<div class="modal-backdrop" id="' . esc($id) . 'Backdrop"></div>';
        $html .= '<div class="modal" id="' . esc($id) . '">';
        $html .= '<div class="modal-dialog';
        if ($size) $html .= ' modal-' . esc($size);
        $html .= '">';

        // Header
        $html .= '<div class="modal-header">';
        $html .= '<h3 class="modal-title">' . esc($title) . '</h3>';
        $html .= '<button type="button" class="modal-close" onclick="closeModal(\'' . esc($id) . '\')">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</button>';
        $html .= '</div>';

        // Body
        $html .= '<div class="modal-body">' . $content . '</div>';

        // Footer
        if ($footer) {
            $html .= '<div class="modal-footer">' . $footer . '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Build a form group component
     *
     * @param array $options Form group options
     * @return string HTML
     */
    public static function formGroup(array $options = []): string
    {
        $label = $options['label'] ?? '';
        $name = $options['name'] ?? '';
        $type = $options['type'] ?? 'text';
        $value = $options['value'] ?? '';
        $placeholder = $options['placeholder'] ?? '';
        $required = $options['required'] ?? false;
        $help = $options['help'] ?? '';
        $error = $options['error'] ?? '';
        $class = $options['class'] ?? '';
        $attributes = $options['attributes'] ?? '';

        $html = '<div class="form-group">';

        // Label
        if ($label) {
            $html .= '<label for="' . esc($name) . '" class="form-label">';
            $html .= esc($label);
            if ($required) {
                $html .= ' <span class="label-required">*</span>';
            }
            $html .= '</label>';
        }

        // Input
        if ($type === 'textarea') {
            $html .= '<textarea class="form-control';
            if ($error) $html .= ' is-invalid';
            if ($class) $html .= ' ' . esc($class);
            $html .= '" id="' . esc($name) . '" name="' . esc($name) . '"';
            if ($placeholder) $html .= ' placeholder="' . esc($placeholder) . '"';
            if ($required) $html .= ' required';
            if ($attributes) $html .= ' ' . $attributes;
            $html .= '>' . esc($value) . '</textarea>';
        } else {
            $html .= '<input type="' . esc($type) . '" class="form-control';
            if ($error) $html .= ' is-invalid';
            if ($class) $html .= ' ' . esc($class);
            $html .= '" id="' . esc($name) . '" name="' . esc($name) . '"';
            if ($value) $html .= ' value="' . esc($value) . '"';
            if ($placeholder) $html .= ' placeholder="' . esc($placeholder) . '"';
            if ($required) $html .= ' required';
            if ($attributes) $html .= ' ' . $attributes;
            $html .= '>';
        }

        // Help text
        if ($help) {
            $html .= '<small class="form-help">' . $help . '</small>';
        }

        // Error message
        if ($error) {
            $html .= '<span class="form-error">' . esc($error) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build a breadcrumb component
     *
     * @param array $items Breadcrumb items
     * @return string HTML
     */
    public static function breadcrumb(array $items = []): string
    {
        if (empty($items)) {
            return '';
        }

        $html = '<nav class="breadcrumb-nav" aria-label="breadcrumb">';
        $html .= '<ol class="breadcrumb">';

        // Home icon
        $html .= '<li class="breadcrumb-item">';
        $html .= '<a href="' . base_url('/') . '"><i class="fas fa-home"></i></a>';
        $html .= '</li>';

        // Items
        $lastIndex = array_key_last($items);
        foreach ($items as $index => $item) {
            $isLast = $index === $lastIndex;
            $html .= '<li class="breadcrumb-item';
            if ($isLast) $html .= ' active" aria-current="page';
            $html .= '">';

            if ($isLast || empty($item['url'])) {
                $html .= esc($item['label']);
            } else {
                $html .= '<a href="' . esc($item['url']) . '">' . esc($item['label']) . '</a>';
            }

            $html .= '</li>';
        }

        $html .= '</ol>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Build a pagination component
     *
     * @param array $options Pagination options
     * @return string HTML
     */
    public static function pagination(array $options = []): string
    {
        $currentPage = $options['current'] ?? 1;
        $totalPages = $options['total'] ?? 1;
        $baseUrl = $options['url'] ?? '';
        $maxLinks = $options['max_links'] ?? 5;

        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav class="pagination-nav"><ul class="pagination">';

        // Previous
        $html .= '<li class="page-item' . ($currentPage <= 1 ? ' disabled' : '') . '">';
        if ($currentPage > 1) {
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">';
        } else {
            $html .= '<span class="page-link">';
        }
        $html .= '<i class="fas fa-chevron-left"></i>';
        $html .= $currentPage > 1 ? '</a>' : '</span>';
        $html .= '</li>';

        // Pages
        $start = max(1, $currentPage - floor($maxLinks / 2));
        $end = min($totalPages, $start + $maxLinks - 1);

        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i === $currentPage ? ' active' : '') . '">';
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a>';
            $html .= '</li>';
        }

        // Next
        $html .= '<li class="page-item' . ($currentPage >= $totalPages ? ' disabled' : '') . '">';
        if ($currentPage < $totalPages) {
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">';
        } else {
            $html .= '<span class="page-link">';
        }
        $html .= '<i class="fas fa-chevron-right"></i>';
        $html .= $currentPage < $totalPages ? '</a>' : '</span>';
        $html .= '</li>';

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * Get default icon for alert type
     */
    protected static function getAlertIcon(string $type): string
    {
        return match ($type) {
            'success' => 'fa-check-circle',
            'danger', 'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle',
            default => 'fa-info-circle',
        };
    }
}

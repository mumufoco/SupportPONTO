<?php

/**
 * Format Helper
 *
 * Functions for formatting values, hours, currency, etc.
 */

if (!function_exists('format_hours')) {
    /**
     * Format hours from decimal to HH:MM
     *
     * @param float $hours
     * @param bool $showSign
     * @return string
     */
    function format_hours(float $hours, bool $showSign = false): string
    {
        $sign = '';

        if ($showSign && $hours > 0) {
            $sign = '+';
        } elseif ($hours < 0) {
            $sign = '-';
            $hours = abs($hours);
        }

        $h = floor($hours);
        $m = round(($hours - $h) * 60);

        // Handle rounding edge case
        if ($m >= 60) {
            $h++;
            $m = 0;
        }

        return $sign . sprintf('%02d:%02d', $h, $m);
    }
}

if (!function_exists('parse_hours')) {
    /**
     * Parse hours from HH:MM format to decimal
     *
     * @param string $time
     * @return float
     */
    function parse_hours(string $time): float
    {
        $parts = explode(':', $time);

        if (count($parts) < 2) {
            return 0.0;
        }

        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];

        return $hours + ($minutes / 60);
    }
}

if (!function_exists('format_currency_br')) {
    /**
     * Format currency to Brazilian Real (R$ 1.234,56)
     *
     * @param float $value
     * @param bool $showSymbol
     * @return string
     */
    function format_currency_br(float $value, bool $showSymbol = true): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return $showSymbol ? "R$ {$formatted}" : $formatted;
    }
}

if (!function_exists('parse_currency_br')) {
    /**
     * Parse Brazilian currency format to float
     *
     * @param string $value
     * @return float
     */
    function parse_currency_br(string $value): float
    {
        // Remove R$ and spaces
        $value = str_replace(['R$', ' '], '', $value);

        // Replace comma with dot
        $value = str_replace(',', '.', $value);

        // Remove thousand separators
        $value = str_replace('.', '', $value);

        // Add decimal separator back
        $lastDot = strrpos($value, '.');
        if ($lastDot !== false) {
            $value = substr($value, 0, $lastDot) . '.' . substr($value, $lastDot + 1);
        }

        return (float) $value;
    }
}

if (!function_exists('format_number_br')) {
    /**
     * Format number to Brazilian format (1.234,56)
     *
     * @param float $number
     * @param int $decimals
     * @return string
     */
    function format_number_br(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals, ',', '.');
    }
}

if (!function_exists('format_phone_br')) {
    /**
     * Format Brazilian phone number
     * (11) 98765-4321 or (11) 3456-7890
     *
     * @param string $phone
     * @return string
     */
    function format_phone_br(string $phone): string
    {
        // Remove formatting
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check length
        if (strlen($phone) == 11) {
            // Mobile: (11) 98765-4321
            return '(' . substr($phone, 0, 2) . ') ' .
                   substr($phone, 2, 5) . '-' .
                   substr($phone, 7, 4);
        } elseif (strlen($phone) == 10) {
            // Landline: (11) 3456-7890
            return '(' . substr($phone, 0, 2) . ') ' .
                   substr($phone, 2, 4) . '-' .
                   substr($phone, 6, 4);
        }

        return $phone;
    }
}

if (!function_exists('format_cep')) {
    /**
     * Format Brazilian postal code (CEP)
     * 12345-678
     *
     * @param string $cep
     * @return string
     */
    function format_cep(string $cep): string
    {
        // Remove formatting
        $cep = preg_replace('/[^0-9]/', '', $cep);

        // Check length
        if (strlen($cep) != 8) {
            return $cep;
        }

        // Apply mask
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
}

if (!function_exists('format_percentage')) {
    /**
     * Format percentage (12.34%)
     *
     * @param float $value
     * @param int $decimals
     * @param bool $showSymbol
     * @return string
     */
    function format_percentage(float $value, int $decimals = 2, bool $showSymbol = true): string
    {
        $formatted = number_format($value, $decimals, ',', '.');

        return $showSymbol ? "{$formatted}%" : $formatted;
    }
}

if (!function_exists('format_file_size')) {
    /**
     * Format file size (bytes to human readable)
     *
     * @param int $bytes
     * @param int $decimals
     * @return string
     */
    function format_file_size(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $decimals) . ' ' . $units[$i];
    }
}

if (!function_exists('format_balance')) {
    /**
     * Format hours balance with sign and color class
     *
     * @param float $balance
     * @return array ['formatted' => string, 'class' => string]
     */
    function format_balance(float $balance): array
    {
        $formatted = format_hours(abs($balance), false);

        if ($balance > 0) {
            return [
                'formatted' => '+' . $formatted,
                'class' => 'text-success',
                'color' => 'green',
            ];
        } elseif ($balance < 0) {
            return [
                'formatted' => '-' . $formatted,
                'class' => 'text-danger',
                'color' => 'red',
            ];
        }

        return [
            'formatted' => $formatted,
            'class' => 'text-muted',
            'color' => 'gray',
        ];
    }
}

if (!function_exists('truncate_text')) {
    /**
     * Truncate text to specified length
     *
     * @param string $text
     * @param int $length
     * @param string $suffix
     * @return string
     */
    function truncate_text(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('format_coordinates')) {
    /**
     * Format GPS coordinates
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $decimals
     * @return string
     */
    function format_coordinates(float $latitude, float $longitude, int $decimals = 6): string
    {
        $lat = number_format($latitude, $decimals, '.', '');
        $lon = number_format($longitude, $decimals, '.', '');

        return "{$lat}, {$lon}";
    }
}

if (!function_exists('format_distance')) {
    /**
     * Format distance in meters
     *
     * @param float $meters
     * @return string
     */
    function format_distance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . ' metros';
        }

        $km = $meters / 1000;
        return number_format($km, 2, ',', '.') . ' km';
    }
}

if (!function_exists('format_nsr')) {
    /**
     * Format NSR (Número Sequencial de Registro) with leading zeros
     *
     * @param int $nsr
     * @param int $length
     * @return string
     */
    function format_nsr(int $nsr, int $length = 10): string
    {
        return str_pad($nsr, $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('format_employee_code')) {
    /**
     * Format employee unique code
     *
     * @param string $code
     * @return string
     */
    function format_employee_code(string $code): string
    {
        return strtoupper($code);
    }
}

if (!function_exists('pluralize')) {
    /**
     * Simple Portuguese pluralization
     *
     * @param int $count
     * @param string $singular
     * @param string|null $plural
     * @return string
     */
    function pluralize(int $count, string $singular, ?string $plural = null): string
    {
        if ($count === 1) {
            return "1 {$singular}";
        }

        $plural = $plural ?? $singular . 's';
        return "{$count} {$plural}";
    }
}

if (!function_exists('format_list')) {
    /**
     * Format array as comma-separated list
     *
     * @param array $items
     * @param string $lastSeparator
     * @return string
     */
    function format_list(array $items, string $lastSeparator = ' e '): string
    {
        if (empty($items)) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);
        return implode(', ', $items) . $lastSeparator . $last;
    }
}

<?php

/**
 * String Helper
 *
 * Functions for string manipulation, slugs, masks, etc.
 */

if (!function_exists('create_slug')) {
    /**
     * Create URL-friendly slug from string
     *
     * @param string $text
     * @param string $separator
     * @return string
     */
    function create_slug(string $text, string $separator = '-'): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace accented characters
        $accents = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        $text = strtr($text, $accents);

        // Remove special characters
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);

        // Replace whitespace and multiple hyphens with separator
        $text = preg_replace('/[\s-]+/', $separator, $text);

        // Trim separators from ends
        $text = trim($text, $separator);

        return $text;
    }
}

if (!function_exists('remove_accents')) {
    /**
     * Remove accents from string
     *
     * @param string $text
     * @return string
     */
    function remove_accents(string $text): string
    {
        $accents = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr($text, $accents);
    }
}

if (!function_exists('generate_random_string')) {
    /**
     * Generate random alphanumeric string
     *
     * @param int $length
     * @param bool $uppercase
     * @param bool $numbers
     * @return string
     */
    function generate_random_string(int $length = 10, bool $uppercase = true, bool $numbers = true): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz';

        if ($uppercase) {
            $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if ($numbers) {
            $chars .= '0123456789';
        }

        $string = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $string .= $chars[random_int(0, $max)];
        }

        return $string;
    }
}

if (!function_exists('generate_unique_code')) {
    /**
     * Generate unique employee code (6 alphanumeric characters)
     *
     * @param int $length
     * @return string
     */
    function generate_unique_code(int $length = 6): string
    {
        return strtoupper(generate_random_string($length, true, true));
    }
}

if (!function_exists('str_contains_any')) {
    /**
     * Check if string contains any of the given substrings
     *
     * @param string $haystack
     * @param array $needles
     * @return bool
     */
    function str_contains_any(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('str_starts_with_any')) {
    /**
     * Check if string starts with any of the given prefixes
     *
     * @param string $haystack
     * @param array $prefixes
     * @return bool
     */
    function str_starts_with_any(string $haystack, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (strpos($haystack, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('limit_words')) {
    /**
     * Limit string to specified number of words
     *
     * @param string $text
     * @param int $limit
     * @param string $suffix
     * @return string
     */
    function limit_words(string $text, int $limit = 10, string $suffix = '...'): string
    {
        $words = explode(' ', $text);

        if (count($words) <= $limit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $limit)) . $suffix;
    }
}

if (!function_exists('excerpt')) {
    /**
     * Create excerpt from text
     *
     * @param string $text
     * @param int $length
     * @return string
     */
    function excerpt(string $text, int $length = 200): string
    {
        // Remove HTML tags
        $text = strip_tags($text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Truncate
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}

if (!function_exists('highlight_search')) {
    /**
     * Highlight search terms in text
     *
     * @param string $text
     * @param string $search
     * @param string $class
     * @return string
     */
    function highlight_search(string $text, string $search, string $class = 'highlight'): string
    {
        if (empty($search)) {
            return $text;
        }

        $pattern = '/(' . preg_quote($search, '/') . ')/i';
        return preg_replace($pattern, '<mark class="' . $class . '">$1</mark>', $text);
    }
}

if (!function_exists('initials')) {
    /**
     * Get initials from name
     *
     * @param string $name
     * @param int $limit
     * @return string
     */
    function initials(string $name, int $limit = 2): string
    {
        $words = explode(' ', trim($name));
        $initials = '';

        foreach (array_slice($words, 0, $limit) as $word) {
            if (!empty($word)) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }

        return $initials;
    }
}

if (!function_exists('snake_to_camel')) {
    /**
     * Convert snake_case to camelCase
     *
     * @param string $text
     * @return string
     */
    function snake_to_camel(string $text): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $text))));
    }
}

if (!function_exists('camel_to_snake')) {
    /**
     * Convert camelCase to snake_case
     *
     * @param string $text
     * @return string
     */
    function camel_to_snake(string $text): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $text));
    }
}

if (!function_exists('normalize_whitespace')) {
    /**
     * Normalize whitespace in text
     *
     * @param string $text
     * @return string
     */
    function normalize_whitespace(string $text): string
    {
        // Replace multiple spaces with single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        return trim($text);
    }
}

if (!function_exists('obfuscate_string')) {
    /**
     * Obfuscate string for security (like credit card numbers)
     *
     * @param string $text
     * @param int $visibleChars Number of visible characters at end
     * @return string
     */
    function obfuscate_string(string $text, int $visibleChars = 4): string
    {
        $length = strlen($text);

        if ($length <= $visibleChars) {
            return $text;
        }

        return str_repeat('*', $length - $visibleChars) . substr($text, -$visibleChars);
    }
}

if (!function_exists('apply_mask')) {
    /**
     * Apply mask to string
     * Mask format: # = number, A = letter, * = any
     *
     * @param string $value
     * @param string $mask
     * @return string
     */
    function apply_mask(string $value, string $mask): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', '', $value);
        $result = '';
        $valueIndex = 0;

        for ($i = 0; $i < strlen($mask); $i++) {
            if ($valueIndex >= strlen($value)) {
                break;
            }

            $maskChar = $mask[$i];
            $valueChar = $value[$valueIndex];

            if ($maskChar === '#' && is_numeric($valueChar)) {
                $result .= $valueChar;
                $valueIndex++;
            } elseif ($maskChar === 'A' && ctype_alpha($valueChar)) {
                $result .= $valueChar;
                $valueIndex++;
            } elseif ($maskChar === '*') {
                $result .= $valueChar;
                $valueIndex++;
            } else {
                $result .= $maskChar;
            }
        }

        return $result;
    }
}

if (!function_exists('clean_string')) {
    /**
     * Clean string (remove special characters)
     *
     * @param string $text
     * @param string $allowed
     * @return string
     */
    function clean_string(string $text, string $allowed = 'a-zA-Z0-9\s'): string
    {
        return preg_replace('/[^' . $allowed . ']/', '', $text);
    }
}

if (!function_exists('capitalize_first')) {
    /**
     * Capitalize first letter of each word (Title Case)
     *
     * @param string $text
     * @return string
     */
    function capitalize_first(string $text): string
    {
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}

if (!function_exists('capitalize_sentence')) {
    /**
     * Capitalize first letter of sentence
     *
     * @param string $text
     * @return string
     */
    function capitalize_sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}

if (!function_exists('str_random_color')) {
    /**
     * Generate random hex color
     *
     * @return string
     */
    function str_random_color(): string
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('str_avatar_color')) {
    /**
     * Generate consistent color from string (for avatars)
     *
     * @param string $text
     * @return string
     */
    function str_avatar_color(string $text): string
    {
        $colors = [
            '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6',
            '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#16a085',
        ];

        $hash = crc32($text);
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }
}

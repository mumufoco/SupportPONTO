<?php

/**
 * DateTime Helper
 *
 * Functions for date/time formatting in Brazilian Portuguese
 */

if (!function_exists('format_date_br')) {
    /**
     * Format date to Brazilian format (dd/mm/yyyy)
     *
     * @param string|null $date
     * @return string
     */
    function format_date_br(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            return date('d/m/Y', strtotime($date));
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('format_datetime_br')) {
    /**
     * Format datetime to Brazilian format (dd/mm/yyyy HH:mm)
     *
     * @param string|null $datetime
     * @param bool $showSeconds
     * @return string
     */
    function format_datetime_br(?string $datetime, bool $showSeconds = false): string
    {
        if (!$datetime) {
            return '';
        }

        try {
            $format = $showSeconds ? 'd/m/Y H:i:s' : 'd/m/Y H:i';
            return date($format, strtotime($datetime));
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}

if (!function_exists('format_time')) {
    /**
     * Format time (HH:mm or HH:mm:ss)
     *
     * @param string|null $time
     * @param bool $showSeconds
     * @return string
     */
    function format_time(?string $time, bool $showSeconds = false): string
    {
        if (!$time) {
            return '';
        }

        try {
            $format = $showSeconds ? 'H:i:s' : 'H:i';
            return date($format, strtotime($time));
        } catch (\Exception $e) {
            return $time;
        }
    }
}

if (!function_exists('get_day_of_week_br')) {
    /**
     * Get day of week in Portuguese
     *
     * @param string $date
     * @param bool $short
     * @return string
     */
    function get_day_of_week_br(string $date, bool $short = false): string
    {
        $dayOfWeek = date('l', strtotime($date));

        $days = [
            'Monday' => $short ? 'Seg' : 'Segunda-feira',
            'Tuesday' => $short ? 'Ter' : 'Terça-feira',
            'Wednesday' => $short ? 'Qua' : 'Quarta-feira',
            'Thursday' => $short ? 'Qui' : 'Quinta-feira',
            'Friday' => $short ? 'Sex' : 'Sexta-feira',
            'Saturday' => $short ? 'Sáb' : 'Sábado',
            'Sunday' => $short ? 'Dom' : 'Domingo',
        ];

        return $days[$dayOfWeek] ?? $dayOfWeek;
    }
}

if (!function_exists('get_month_br')) {
    /**
     * Get month name in Portuguese
     *
     * @param int|string $month Month number (1-12) or date string
     * @param bool $short
     * @return string
     */
    function get_month_br($month, bool $short = false): string
    {
        // If month is a date string, extract month number
        if (is_string($month) && strlen($month) > 2) {
            $month = (int) date('n', strtotime($month));
        }

        $month = (int) $month;

        $months = [
            1 => $short ? 'Jan' : 'Janeiro',
            2 => $short ? 'Fev' : 'Fevereiro',
            3 => $short ? 'Mar' : 'Março',
            4 => $short ? 'Abr' : 'Abril',
            5 => $short ? 'Mai' : 'Maio',
            6 => $short ? 'Jun' : 'Junho',
            7 => $short ? 'Jul' : 'Julho',
            8 => $short ? 'Ago' : 'Agosto',
            9 => $short ? 'Set' : 'Setembro',
            10 => $short ? 'Out' : 'Outubro',
            11 => $short ? 'Nov' : 'Novembro',
            12 => $short ? 'Dez' : 'Dezembro',
        ];

        return $months[$month] ?? '';
    }
}

if (!function_exists('time_ago_br')) {
    /**
     * Get relative time in Portuguese (e.g., "há 5 minutos")
     *
     * @param string $datetime
     * @return string
     */
    function time_ago_br(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'agora mesmo';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "há {$minutes} " . ($minutes == 1 ? 'minuto' : 'minutos');
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "há {$hours} " . ($hours == 1 ? 'hora' : 'horas');
        }

        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return "há {$days} " . ($days == 1 ? 'dia' : 'dias');
        }

        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return "há {$weeks} " . ($weeks == 1 ? 'semana' : 'semanas');
        }

        if ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return "há {$months} " . ($months == 1 ? 'mês' : 'meses');
        }

        $years = floor($diff / 31536000);
        return "há {$years} " . ($years == 1 ? 'ano' : 'anos');
    }
}

if (!function_exists('calculate_age')) {
    /**
     * Calculate age from birthdate
     *
     * @param string $birthdate
     * @return int
     */
    function calculate_age(string $birthdate): int
    {
        $birthDate = new DateTime($birthdate);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;

        return $age;
    }
}

if (!function_exists('is_business_day')) {
    /**
     * Check if date is a business day (Monday-Friday)
     *
     * @param string $date
     * @return bool
     */
    function is_business_day(string $date): bool
    {
        $dayOfWeek = (int) date('N', strtotime($date));
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }
}

if (!function_exists('get_business_days')) {
    /**
     * Get number of business days between two dates
     *
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    function get_business_days(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        $businessDays = 0;

        foreach ($period as $date) {
            if (is_business_day($date->format('Y-m-d'))) {
                $businessDays++;
            }
        }

        return $businessDays;
    }
}

if (!function_exists('format_month_year_br')) {
    /**
     * Format month/year to Brazilian format (Mês/Ano)
     *
     * @param string $monthYear Format: Y-m
     * @return string
     */
    function format_month_year_br(string $monthYear): string
    {
        try {
            $date = $monthYear . '-01';
            $month = get_month_br(date('n', strtotime($date)));
            $year = date('Y', strtotime($date));

            return "{$month}/{$year}";
        } catch (\Exception $e) {
            return $monthYear;
        }
    }
}

if (!function_exists('get_current_month_range')) {
    /**
     * Get current month date range
     *
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    function get_current_month_range(): array
    {
        return [
            'start' => date('Y-m-01'),
            'end' => date('Y-m-t'),
        ];
    }
}

if (!function_exists('get_last_month_range')) {
    /**
     * Get last month date range
     *
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    function get_last_month_range(): array
    {
        return [
            'start' => date('Y-m-01', strtotime('first day of last month')),
            'end' => date('Y-m-t', strtotime('last day of last month')),
        ];
    }
}


if (!function_exists('normalize_month_reference')) {
    /**
     * Resolve a month reference (Y-m) or fall back to current month.
     */
    function normalize_month_reference(?string $month, ?string $fallback = null): string
    {
        $candidate = trim((string) ($month ?? ''));
        $resolvedFallback = $fallback ?? date('Y-m');

        if ($candidate === '') {
            return $resolvedFallback;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $candidate)) {
            return $resolvedFallback;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m', $candidate);
        if ($date === false || $date->format('Y-m') !== $candidate) {
            return $resolvedFallback;
        }

        return $candidate;
    }
}

if (!function_exists('get_month_datetime_range')) {
    /**
     * Build an inclusive-exclusive datetime range for a month reference.
     *
     * @return array{month:string,start_date:string,end_date:string,start_at:string,end_at:string}
     */
    function get_month_datetime_range(?string $month, ?string $fallback = null): array
    {
        $resolvedMonth = normalize_month_reference($month, $fallback);
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $resolvedMonth . '-01');
        $start = $start ?: new \DateTimeImmutable(date('Y-m-01'));
        $end = $start->modify('first day of next month');

        return [
            'month' => $resolvedMonth,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->modify('-1 day')->format('Y-m-d'),
            'start_at' => $start->format('Y-m-d 00:00:00'),
            'end_at' => $end->format('Y-m-d 00:00:00'),
        ];
    }
}

if (!function_exists('format_duration')) {
    /**
     * Format duration in seconds to human readable format
     *
     * @param int $seconds
     * @return string
     */
    function format_duration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $secs > 0 ? "{$minutes}m {$secs}s" : "{$minutes}m";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }
}

if (!function_exists('parse_date_br')) {
    /**
     * Parse Brazilian date format (dd/mm/yyyy) to Y-m-d
     *
     * @param string $dateBr
     * @return string|null
     */
    function parse_date_br(string $dateBr): ?string
    {
        try {
            $parts = explode('/', $dateBr);

            if (count($parts) !== 3) {
                return null;
            }

            $day = (int) $parts[0];
            $month = (int) $parts[1];
            $year = (int) $parts[2];

            if (!checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        } catch (\Exception $e) {
            return null;
        }
    }
}

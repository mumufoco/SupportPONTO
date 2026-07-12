<?php

namespace App\Support\Formatters;

/**
 * MELHORIA 4: Formatadores brasileiros como classe com namespace.
 *
 * Migração gradual das funções globais em format_helper.php e custom_helper.php
 * para uma classe testável, com namespace, sem conflitos de nome.
 *
 * As funções globais são mantidas como wrappers retrocompatíveis:
 *   function format_cpf(string $cpf): string {
 *       return BrazilianFormatter::cpf($cpf);
 *   }
 *
 * Vantagens sobre helpers globais:
 * - Mockável em testes unitários
 * - Autoload sem require_once manual
 * - Sem risco de conflito de nomes globais
 * - PHPStan analisa totalmente
 */
final class BrazilianFormatter
{
    // ── CPF ──────────────────────────────────────────────────────────────────

    public static function cpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return $cpf;
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public static function cpfMasked(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return '***.***.***-**';
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . '***-**';
    }

    // ── Telefone ─────────────────────────────────────────────────────────────

    public static function phone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return match (strlen($d)) {
            11     => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7),
            10     => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6),
            default => $phone,
        };
    }

    // ── Moeda ────────────────────────────────────────────────────────────────

    public static function currency(float $value, string $symbol = 'R$'): string
    {
        return $symbol . ' ' . number_format($value, 2, ',', '.');
    }

    public static function currencyCompact(float $value): string
    {
        if ($value >= 1_000_000) return 'R$ ' . number_format($value / 1_000_000, 1, ',', '.') . 'M';
        if ($value >= 1_000)     return 'R$ ' . number_format($value / 1_000, 1, ',', '.') . 'K';
        return self::currency($value);
    }

    // ── Data e Hora ───────────────────────────────────────────────────────────

    public static function date(string $date, string $fromFormat = 'Y-m-d'): string
    {
        if (empty($date) || $date === '0000-00-00') return '—';
        $ts = \DateTime::createFromFormat($fromFormat, $date);
        return $ts ? $ts->format('d/m/Y') : $date;
    }

    public static function dateTime(string $dateTime): string
    {
        if (empty($dateTime)) return '—';
        $ts = strtotime($dateTime);
        return $ts ? date('d/m/Y H:i', $ts) : $dateTime;
    }

    public static function monthYear(string $yearMonth): string
    {
        $months = [
            '01' => 'Janeiro',  '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril',    '05' => 'Maio',       '06' => 'Junho',
            '07' => 'Julho',    '08' => 'Agosto',     '09' => 'Setembro',
            '10' => 'Outubro',  '11' => 'Novembro',   '12' => 'Dezembro',
        ];
        [$year, $month] = explode('-', $yearMonth . '-01');
        return ($months[$month] ?? $month) . '/' . $year;
    }

    // ── Horas de Trabalho ─────────────────────────────────────────────────────

    /**
     * Converte horas decimais em HH:MM.
     * Exemplo: 8.5 → "08:30" | -1.25 → "-01:15"
     */
    public static function hours(float $hours): string
    {
        $negative = $hours < 0;
        $abs      = abs($hours);
        $h        = (int) $abs;
        $m        = (int) round(($abs - $h) * 60);

        return ($negative ? '-' : '') . sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Converte HH:MM em horas decimais.
     */
    public static function parseHours(string $hoursMinutes): float
    {
        if (str_contains($hoursMinutes, ':')) {
            [$h, $m] = explode(':', $hoursMinutes);
            return (int)$h + ((int)$m / 60);
        }
        return (float) $hoursMinutes;
    }

    // ── CEP ──────────────────────────────────────────────────────────────────

    public static function cep(string $cep): string
    {
        $d = preg_replace('/\D/', '', $cep);
        if (strlen($d) !== 8) return $cep;
        return substr($d, 0, 5) . '-' . substr($d, 5);
    }

    // ── CNPJ ─────────────────────────────────────────────────────────────────

    public static function cnpj(string $cnpj): string
    {
        $d = preg_replace('/\D/', '', $cnpj);
        if (strlen($d) !== 14) return $cnpj;
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
             . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }
}

<?php

/**
 * CPF Helper
 *
 * Functions for CPF validation and formatting
 */

if (!function_exists('validate_cpf')) {
    /**
     * Validate CPF number
     *
     * @param string $cpf
     * @return bool
     */
    function validate_cpf(string $cpf): bool
    {
        // Remove formatting
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Check if has 11 digits
        if (strlen($cpf) != 11) {
            return false;
        }

        // Check for known invalid CPFs
        $invalidCPFs = [
            '00000000000',
            '11111111111',
            '22222222222',
            '33333333333',
            '44444444444',
            '55555555555',
            '66666666666',
            '77777777777',
            '88888888888',
            '99999999999',
        ];

        if (in_array($cpf, $invalidCPFs)) {
            return false;
        }

        // Validate check digits
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('format_cpf')) {
    /**
     * Format CPF with mask (000.000.000-00)
     *
     * @param string $cpf
     * @return string
     */
    function format_cpf(string $cpf): string
    {
        // Remove formatting
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Check length
        if (strlen($cpf) != 11) {
            return $cpf;
        }

        // Apply mask
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' .
               substr($cpf, 9, 2);
    }
}

if (!function_exists('clean_cpf')) {
    /**
     * Remove CPF formatting
     *
     * @param string $cpf
     * @return string
     */
    function clean_cpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }
}

if (!function_exists('mask_cpf')) {
    /**
     * Mask CPF for privacy (000.000.XXX-XX)
     *
     * @param string $cpf
     * @return string
     */
    function mask_cpf(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) != 11) {
            return $cpf;
        }

        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               'XXX-XX';
    }
}

if (!function_exists('validate_cnpj')) {
    /**
     * Validate CNPJ number
     *
     * @param string $cnpj
     * @return bool
     */
    function validate_cnpj(string $cnpj): bool
    {
        // Remove formatting
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Check if has 14 digits
        if (strlen($cnpj) != 14) {
            return false;
        }

        // Check for known invalid CNPJs
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Validate first check digit
        $sum = 0;
        $multiplier = 5;

        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $multiplier;
            $multiplier = ($multiplier == 2) ? 9 : $multiplier - 1;
        }

        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        if ($cnpj[12] != $digit1) {
            return false;
        }

        // Validate second check digit
        $sum = 0;
        $multiplier = 6;

        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $multiplier;
            $multiplier = ($multiplier == 2) ? 9 : $multiplier - 1;
        }

        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        if ($cnpj[13] != $digit2) {
            return false;
        }

        return true;
    }
}

if (!function_exists('format_cnpj')) {
    /**
     * Format CNPJ with mask (00.000.000/0000-00)
     *
     * @param string $cnpj
     * @return string
     */
    function format_cnpj(string $cnpj): string
    {
        // Remove formatting
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Check length
        if (strlen($cnpj) != 14) {
            return $cnpj;
        }

        // Apply mask
        return substr($cnpj, 0, 2) . '.' .
               substr($cnpj, 2, 3) . '.' .
               substr($cnpj, 5, 3) . '/' .
               substr($cnpj, 8, 4) . '-' .
               substr($cnpj, 12, 2);
    }
}

if (!function_exists('generate_cpf')) {
    /**
     * Generate a valid random CPF (for testing purposes only)
     *
     * @return string
     */
    function generate_cpf(): string
    {
        // Generate first 9 digits
        $cpf = '';
        for ($i = 0; $i < 9; $i++) {
            $cpf .= mt_rand(0, 9);
        }

        // Calculate first check digit
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;
        $cpf .= $digit1;

        // Calculate second check digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;
        $cpf .= $digit2;

        return $cpf;
    }
}

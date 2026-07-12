<?php

namespace App\Services\TXT;

class TXTLineFormatter
{
    public function addCommonHeader(array &$lines, string $title, array $companyProfile): void
    {
        $lines[] = str_repeat('=', 80);
        $lines[] = $this->centerText($title, 80);
        $lines[] = str_repeat('=', 80);
        $lines[] = str_pad('Empresa: ' . $companyProfile['companyName'], 80, ' ', STR_PAD_LEFT);
        $lines[] = str_pad('CNPJ: ' . $this->formatCNPJ($companyProfile['companyCNPJ']), 80, ' ', STR_PAD_LEFT);
        $lines[] = str_pad('Data de Geração: ' . date('d/m/Y H:i:s'), 80, ' ', STR_PAD_LEFT);
        $lines[] = str_repeat('=', 80);
        $lines[] = '';
    }

    public function addFiltersSection(array &$lines, array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        $lines[] = 'FILTROS APLICADOS:';
        foreach ($filters as $key => $value) {
            $lines[] = '  ' . ucfirst((string) $key) . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = str_repeat('-', 80);
        $lines[] = '';
    }

    public function centerText(string $text, int $width): string
    {
        return str_pad($text, $width, ' ', STR_PAD_BOTH);
    }

    public function formatCNPJ(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?: '';
        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.'
                . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
        }

        return $cnpj;
    }
}

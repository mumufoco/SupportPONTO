<?php

namespace App\Services\CSV;

use App\Services\Security\FormulaInjectionGuard;

class CSVRowSerializer
{
    public function __construct(
        private readonly string $delimiter = ';',
        private readonly string $enclosure = '"'
    ) {
    }

    public function serialize(array $fields): string
    {
        $row = [];

        foreach ($fields as $field) {
            $value = (string) FormulaInjectionGuard::neutralize((string) $field);
            $value = str_replace($this->enclosure, $this->enclosure . $this->enclosure, $value);

            if (
                str_contains($value, $this->delimiter)
                || str_contains($value, $this->enclosure)
                || str_contains($value, "\n")
                || str_contains($value, "\r")
            ) {
                $value = $this->enclosure . $value . $this->enclosure;
            }

            $row[] = $value;
        }

        return implode($this->delimiter, $row);
    }
}

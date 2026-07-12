<?php

namespace App\ValueObjects;

/**
 * MELHORIA 8: Value Object para CPF.
 *
 * Encapsula validação e formatação do CPF.
 * Uma vez construído, o CPF é garantidamente válido.
 * Elimina validações de CPF espalhadas por controllers, services e models.
 *
 * Uso:
 *   $cpf = new CPF('123.456.789-09');
 *   echo $cpf->formatted();   // "123.456.789-09"
 *   echo $cpf->digits();      // "12345678909"
 *   echo $cpf->masked();      // "123.456.***-**"
 */
final readonly class CPF
{
    private string $digits;

    public function __construct(string $raw)
    {
        $digits = preg_replace('/\D/', '', $raw);

        if (!$this->validate($digits)) {
            throw new \App\Exceptions\ValidationException(
                ['cpf' => "CPF inválido: {$raw}"],
                'INVALID_CPF'
            );
        }

        $this->digits = $digits;
    }

    /** CPF no formato ###.###.###-## */
    public function formatted(): string
    {
        return substr($this->digits, 0, 3) . '.' .
               substr($this->digits, 3, 3) . '.' .
               substr($this->digits, 6, 3) . '-' .
               substr($this->digits, 9, 2);
    }

    /** Apenas os 11 dígitos */
    public function digits(): string
    {
        return $this->digits;
    }

    /** CPF parcialmente mascarado para exibição pública */
    public function masked(): string
    {
        return substr($this->digits, 0, 3) . '.' .
               substr($this->digits, 3, 3) . '.' . '***-**';
    }

    public function equals(CPF $other): bool
    {
        return $this->digits === $other->digits;
    }

    public function __toString(): string
    {
        return $this->formatted();
    }

    /** Tenta criar sem lançar exceção — retorna null se inválido */
    public static function tryFrom(string $raw): ?self
    {
        try {
            return new self($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function validate(string $digits): bool
    {
        if (strlen($digits) !== 11) return false;
        if (preg_match('/^(\d)\1+$/', $digits)) return false; // Todos iguais

        // Primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$digits[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $d1 = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int)$digits[9] !== $d1) return false;

        // Segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$digits[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $d2 = $remainder < 2 ? 0 : 11 - $remainder;
        return (int)$digits[10] === $d2;
    }
}

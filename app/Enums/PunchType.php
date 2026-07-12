<?php

namespace App\Enums;

/**
 * MELHORIA 2: Enum PHP 8.1 para tipos de marcação de ponto.
 *
 * Substitui strings mágicas ('entrada', 'saida' etc.) por valores
 * fortemente tipados. Erros de digitação são detectados em tempo de
 * compilação pelo PHPStan e pelos IDEs.
 *
 * Portaria MTE 671/2021 — tipos canônicos de marcação.
 */
enum PunchType: string
{
    case Entrada         = 'entrada';
    case Saida           = 'saida';
    case IntervaloInicio = 'intervalo_inicio';
    case IntervaloFim    = 'intervalo_fim';

    /** Retorna todos os valores como array (para in_list[] e validações) */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Lista para uso em regras de validação CI4 */
    public static function validationList(): string
    {
        return implode(',', self::values());
    }

    /** Verifica se é um tipo de entrada (início de período) */
    public function isEntry(): bool
    {
        return $this === self::Entrada || $this === self::IntervaloInicio;
    }

    /** Verifica se é um tipo de saída (fim de período) */
    public function isExit(): bool
    {
        return $this === self::Saida || $this === self::IntervaloFim;
    }

    /** Retorna o próximo tipo esperado na sequência de ponto */
    public function nextExpected(): self
    {
        return match($this) {
            self::Entrada         => self::IntervaloInicio,
            self::IntervaloInicio => self::IntervaloFim,
            self::IntervaloFim    => self::Saida,
            self::Saida           => self::Entrada,
        };
    }

    /** Label legível em português para exibição */
    public function label(): string
    {
        return match($this) {
            self::Entrada         => 'Entrada',
            self::Saida           => 'Saída',
            self::IntervaloInicio => 'Início do Intervalo',
            self::IntervaloFim    => 'Fim do Intervalo',
        };
    }
}

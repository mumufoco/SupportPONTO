<?php

namespace App\Enums;

/**
 * Tipos de irregularidade trabalhista detectados automaticamente a partir
 * das marcações de ponto (ver app/Services/Timesheet/PunchComplianceService.php).
 */
enum PunchViolationType: string
{
    case IntervaloIntrajornada = 'intervalo_intrajornada';
    case Interjornada          = 'interjornada';
    case DescansoSemanal       = 'descanso_semanal';
    case JornadaExtraordinaria = 'jornada_extraordinaria';

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

    /** Label legível em português para exibição */
    public function label(): string
    {
        return match ($this) {
            self::IntervaloIntrajornada => 'Intervalo intrajornada insuficiente',
            self::Interjornada          => 'Descanso entre jornadas insuficiente',
            self::DescansoSemanal       => 'Sem descanso semanal remunerado',
            self::JornadaExtraordinaria => 'Jornada extraordinária acima do limite',
        };
    }

    /** Referência legal para exibição ao gestor */
    public function legalReference(): string
    {
        return match ($this) {
            self::IntervaloIntrajornada => 'Art. 71 CLT',
            self::Interjornada          => 'Art. 66 CLT',
            self::DescansoSemanal       => 'Art. 67 CLT',
            self::JornadaExtraordinaria => 'Art. 58/59 CLT',
        };
    }
}

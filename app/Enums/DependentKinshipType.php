<?php

namespace App\Enums;

/**
 * Graus de parentesco de dependentes, conforme Tabela 07 do eSocial
 * (usada nos eventos S-2200/S-2205 para fins de IRRF e salário-família).
 */
enum DependentKinshipType: string
{
    case Filho          = 'filho';
    case FilhoAdotivo   = 'filho_adotivo';
    case Enteado        = 'enteado';
    case Conjuge        = 'conjuge';
    case Companheiro    = 'companheiro';
    case Pai            = 'pai';
    case Mae            = 'mae';
    case Avo            = 'avo';
    case Bisavo         = 'bisavo';
    case Neto           = 'neto';
    case Bisneto        = 'bisneto';
    case Irmao          = 'irmao';
    case MenorPobre     = 'menor_pobre';
    case PessoaIncapaz  = 'pessoa_incapaz';
    case ExConjuge      = 'ex_conjuge';
    case AgregadoOutro  = 'agregado_outro';

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
            self::Filho         => 'Filho(a)',
            self::FilhoAdotivo   => 'Filho(a) adotivo(a)',
            self::Enteado        => 'Enteado(a)',
            self::Conjuge        => 'Cônjuge',
            self::Companheiro    => 'Companheiro(a)',
            self::Pai            => 'Pai',
            self::Mae            => 'Mãe',
            self::Avo            => 'Avô/Avó',
            self::Bisavo         => 'Bisavô/Bisavó',
            self::Neto           => 'Neto(a)',
            self::Bisneto        => 'Bisneto(a)',
            self::Irmao          => 'Irmão/Irmã',
            self::MenorPobre     => 'Menor pobre',
            self::PessoaIncapaz  => 'Pessoa incapaz',
            self::ExConjuge      => 'Ex-cônjuge',
            self::AgregadoOutro  => 'Agregado/Outro',
        };
    }
}

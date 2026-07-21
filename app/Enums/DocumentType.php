<?php

namespace App\Enums;

/**
 * Tipos de documento anexavel ao colaborador -- Aba "Upload de Documentos"
 * do cadastro (RG, CPF, CNH, certificados e outros complementares).
 */
enum DocumentType: string
{
    case Rg = 'rg';
    case Cpf = 'cpf';
    case Cnh = 'cnh';
    case Certificado = 'certificado';
    case Complementar = 'complementar';

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
            self::Rg => 'RG',
            self::Cpf => 'CPF',
            self::Cnh => 'CNH',
            self::Certificado => 'Certificados',
            self::Complementar => 'Documentos complementares',
        };
    }
}

<?php

namespace App\Enums;

/**
 * MELHORIA 2: Enum PHP 8.1 para métodos de registro de ponto.
 *
 * Centraliza os métodos de autenticação aceitos pelo sistema.
 */
enum PunchMethod: string
{
    case Codigo    = 'codigo';
    case CPF       = 'cpf';
    case QRCode    = 'qrcode';
    case Facial    = 'facial';
    case Biometria = 'biometria';
    // v1.1.279 — Métodos de registro manual (justificativa por falha)
    case ManualGestor   = 'manual_gestor';
    case ManualImportado = 'manual_importado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationList(): string
    {
        return implode(',', self::values());
    }

    /** Verifica se o método exige validação biométrica adicional */
    public function requiresBiometric(): bool
    {
        return $this === self::Facial || $this === self::Biometria;
    }


    public function isContingencyMethod(): bool
    {
        return $this === self::Codigo || $this === self::CPF || $this === self::QRCode;
    }

    public function label(): string
    {
        return match($this) {
            self::Codigo          => 'Código de Acesso',
            self::CPF             => 'CPF',
            self::QRCode          => 'QR Code',
            self::Facial          => 'Reconhecimento Facial',
            self::Biometria       => 'Biometria Digital',
            self::ManualGestor    => 'Manual pelo Gestor',
            self::ManualImportado => 'Manual Importado',
        };
    }
}

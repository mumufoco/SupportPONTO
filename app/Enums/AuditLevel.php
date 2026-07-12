<?php

namespace App\Enums;

/**
 * MELHORIA 2: Enum PHP 8.1 para níveis de severidade de auditoria.
 */
enum AuditLevel: string
{
    case Info     = 'info';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationList(): string
    {
        return implode(',', self::values());
    }

    public function isAlert(): bool
    {
        return $this === self::Warning || $this === self::Error || $this === self::Critical;
    }

    public function label(): string
    {
        return match($this) {
            self::Info     => 'Informação',
            self::Warning  => 'Aviso',
            self::Error    => 'Erro',
            self::Critical => 'Crítico',
        };
    }
}

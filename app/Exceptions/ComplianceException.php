<?php
namespace App\Exceptions;

/**
 * Violação de conformidade legal (MTE 671/2021, LGPD etc.)
 * Retorna HTTP 423 Locked — o recurso está bloqueado por razão legal.
 */
class ComplianceException extends BusinessException
{
    public function __construct(string $message, string $regulation = 'COMPLIANCE')
    {
        parent::__construct($message, $regulation, 423);
    }
}

<?php

namespace App\Exceptions;

/**
 * MELHORIA 10: Exceção base para erros de negócio previsíveis.
 *
 * Diferencia erros esperados (regras de negócio violadas) de erros
 * inesperados (bugs, falhas de infraestrutura). O Handler central
 * trata cada tipo de forma adequada: BusinessExceptions viram respostas
 * HTTP limpas; outras exceções viram HTTP 500 com log de erro.
 *
 * Hierarquia:
 *  BusinessException         — base (422 Unprocessable Entity)
 *  ├── ValidationException   — erro de validação (422)
 *  ├── NotFoundException     — recurso não encontrado (404)
 *  ├── ForbiddenException    — acesso negado (403)
 *  ├── UnauthorizedException — não autenticado (401)
 *  ├── ConflictException     — conflito de estado (409, ex: ponto duplicado)
 *  └── ComplianceException   — violação de conformidade legal (423 Locked)
 */
class BusinessException extends \RuntimeException
{
    public function __construct(
        string                    $message,
        public readonly string    $errorCode   = 'BUSINESS_ERROR',
        public readonly int       $httpStatus  = 422,
        public readonly array     $context     = [],
        ?\Throwable               $previous    = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** Cria exceção a partir de array de erros (ex: validação CI4) */
    public static function fromErrors(array $errors, string $code = 'VALIDATION_ERROR'): static
    {
        $message = implode(' ', array_values($errors));
        return new static($message, $code, 422, ['errors' => $errors]);
    }
}

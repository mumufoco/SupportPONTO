<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exceção base para falhas previsíveis em operações de domínio/serviço.
 *
 * Deve ser usada quando a regra de negócio falha de forma controlada e o
 * chamador precisa diferenciar o erro de bugs ou falhas inesperadas.
 */
class DomainOperationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        public readonly string $domainCode = 'DOMAIN_OPERATION_FAILED',
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

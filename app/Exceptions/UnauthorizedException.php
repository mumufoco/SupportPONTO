<?php
namespace App\Exceptions;

class UnauthorizedException extends BusinessException
{
    public function __construct(string $message = 'Autenticação necessária.')
    {
        parent::__construct($message, 'UNAUTHORIZED', 401);
    }
}

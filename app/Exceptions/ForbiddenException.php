<?php
namespace App\Exceptions;

class ForbiddenException extends BusinessException
{
    public function __construct(string $action = 'realizar esta ação')
    {
        parent::__construct(
            "Você não tem permissão para {$action}.",
            'FORBIDDEN',
            403
        );
    }
}

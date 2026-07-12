<?php
namespace App\Exceptions;

class ConflictException extends BusinessException
{
    public function __construct(string $message, string $code = 'CONFLICT')
    {
        parent::__construct($message, $code, 409);
    }
}

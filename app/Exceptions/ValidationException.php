<?php

namespace App\Exceptions;

class ValidationException extends BusinessException
{
    public function __construct(array $errors, string $code = 'VALIDATION_ERROR')
    {
        parent::__construct(
            implode(' ', array_values($errors)),
            $code,
            422,
            ['errors' => $errors]
        );
    }
}

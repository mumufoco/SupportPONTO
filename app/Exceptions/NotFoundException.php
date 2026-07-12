<?php
namespace App\Exceptions;

class NotFoundException extends BusinessException
{
    public function __construct(string $resource = 'Recurso', ?int $id = null)
    {
        $message = $id ? "{$resource} #{$id} não encontrado." : "{$resource} não encontrado.";
        parent::__construct($message, 'NOT_FOUND', 404);
    }
}

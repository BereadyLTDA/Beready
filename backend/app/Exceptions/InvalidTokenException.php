<?php

namespace App\Exceptions;

class InvalidTokenException extends \RuntimeException
{
    public function __construct(string $message = 'Token inválido ou expirado')
    {
        parent::__construct($message, 400);
    }
}

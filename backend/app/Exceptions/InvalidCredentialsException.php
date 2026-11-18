<?php

namespace App\Exceptions;

class InvalidCredentialsException extends \RuntimeException
{
    public function __construct(string $message = 'Credenciais inválidas')
    {
        parent::__construct($message, 401);
    }
}

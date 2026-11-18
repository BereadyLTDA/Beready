<?php

namespace App\Exceptions;

class UserNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Usuário não encontrado')
    {
        parent::__construct($message, 404);
    }
}

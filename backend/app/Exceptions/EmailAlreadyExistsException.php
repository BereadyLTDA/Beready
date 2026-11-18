<?php

namespace App\Exceptions;

class EmailAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $message = 'E-mail já cadastrado')
    {
        parent::__construct($message, 409);
    }
}

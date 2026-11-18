<?php

namespace App\Exceptions;

class WeakPasswordException extends \InvalidArgumentException
{
    public function __construct(string $message = 'A senha é muito fraca')
    {
        parent::__construct($message, 400);
    }
}

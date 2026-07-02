<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InvalidTokenException extends RuntimeException
{
    public function __construct(string $message = "Token inválido ou expirado")
    {
        parent::__construct($message, 400);
    }
}
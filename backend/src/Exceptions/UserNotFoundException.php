<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public function __construct(string $message = "Usuário não encontrado")
    {
        parent::__construct($message, 404);
    }
}
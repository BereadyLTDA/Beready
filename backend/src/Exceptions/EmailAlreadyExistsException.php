<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class EmailAlreadyExistsException extends RuntimeException
{
    public function __construct(string $message = "E-mail já cadastrado")
    {
        parent::__construct($message, 409);
    }
}
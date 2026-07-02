<?php
declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class WeakPasswordException extends InvalidArgumentException
{
    public function __construct(string $message = "A senha é muito fraca")
    {
        parent::__construct($message, 400);
    }
}
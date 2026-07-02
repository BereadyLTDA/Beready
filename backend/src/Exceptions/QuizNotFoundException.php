<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class QuizNotFoundException extends RuntimeException
{
    public function __construct(string $message = "Quiz não encontrado")
    {
        parent::__construct($message, 404);
    }
}
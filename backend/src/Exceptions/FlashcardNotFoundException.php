<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class FlashcardNotFoundException extends RuntimeException
{
    public function __construct(string $message = "Flashcard não encontrado")
    {
        parent::__construct($message, 404);
    }
}
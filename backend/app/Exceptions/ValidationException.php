<?php

namespace App\Exceptions;

/**
 * Erro de validação de regra de negócio. Vira 422 com `errors` no formato herdado do CakePHP:
 * { campo: { regra: mensagem } } (ver Controller::errorResponse e bootstrap/app.php).
 */
class ValidationException extends \RuntimeException
{
    /**
     * @param  array<string, array<string, mixed>>  $errors
     */
    public function __construct(string $message, private array $errors = [])
    {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

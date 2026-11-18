<?php

namespace App\Exceptions;

/**
 * Exceção cuja mensagem e status (inclusive 5xx, como 502/503) já foram pensados para o usuário:
 * o Controller::errorResponse devolve os dois como estão, sem trocar por "Erro interno".
 */
interface ErroComStatus
{
    public function status(): int;
}

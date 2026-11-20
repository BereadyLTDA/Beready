<?php

namespace App\Exceptions;

/**
 * O aluno concluiu um nivelamento há menos de 30 dias: refazer agora não mediria evolução.
 */
class NivelamentoBloqueadoException extends \RuntimeException implements ErroComStatus
{
    public function status(): int
    {
        return 409;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Traits;

use App\Exceptions\GeracaoException;
use App\Exceptions\ValidationException;
use Cake\ORM\Exception\PersistenceFailedException;

/**
 * Converte exceções dos services em respostas JSON sem expor detalhes internos.
 * Usar apenas em subclasses de AppController.
 */
trait ResourceErrorTrait
{
    protected function errorResponse(\Throwable $e, string $fallbackMessage = 'Erro interno do servidor')
    {
        if ($e instanceof PersistenceFailedException) {
            return $this->jsonError($fallbackMessage, 422, $e->getEntity()->getErrors());
        }

        if ($e instanceof ValidationException) {
            return $this->jsonError($e->getMessage(), 422, $e->getErrors());
        }

        // Falhas da IA já trazem status e mensagem pensados para o usuário (inclusive 502/503)
        if ($e instanceof GeracaoException) {
            return $this->jsonError($e->getMessage(), $e->getCode());
        }

        $status = $e instanceof \InvalidArgumentException ? 400 : $this->httpStatusFrom($e);

        if ($status >= 500) {
            error_log(sprintf('[%s] %s: %s', static::class, get_class($e), $e->getMessage()));

            return $this->jsonError($fallbackMessage, 500);
        }

        return $this->jsonError($e->getMessage(), $status);
    }
}

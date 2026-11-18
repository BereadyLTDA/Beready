<?php

namespace App\Http\Controllers;

use App\Exceptions\ErroComStatus;
use App\Exceptions\ValidationException;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Base dos controllers da API. Mantém o formato de resposta da versão em CakePHP:
 * sucesso { success: true, message, data } e erro { success: false, message, errors? }.
 */
abstract class Controller
{
    /**
     * ID do usuário autenticado, definido pelo middleware AutenticaJwt.
     */
    protected function currentUserId(): int
    {
        $userId = request()->attributes->get('user_id');
        if (! $userId) {
            throw new UnauthorizedHttpException('Bearer', 'Usuário não autenticado');
        }

        return (int) $userId;
    }

    /**
     * Role consultada no banco (não no token), para refletir rebaixamentos na hora.
     *
     * O resultado fica guardado no próprio request, não no controller: o Laravel reaproveita a
     * instância do controller entre requisições da mesma rota (e o Octane mantém o processo vivo).
     */
    protected function isAdmin(): bool
    {
        $request = request();
        if (! $request->attributes->has('is_admin')) {
            $request->attributes->set('is_admin', app(PermissionService::class)->isAdmin($this->currentUserId()));
        }

        return (bool) $request->attributes->get('is_admin');
    }

    /**
     * Dono do recurso ou admin.
     */
    protected function canAccessUser(int $usuarioId): bool
    {
        return $usuarioId === $this->currentUserId() || $this->isAdmin();
    }

    /**
     * Corpo JSON da requisição (sempre um array).
     *
     * @return array<string, mixed>
     */
    protected function requestData(Request $request): array
    {
        $data = $request->all();

        return is_array($data) ? $data : [];
    }

    protected function jsonSuccess(mixed $data = null, string $message = 'success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function jsonError(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    /**
     * Converte exceções dos services em respostas JSON sem expor detalhes internos
     * (equivalente ao ResourceErrorTrait da versão em CakePHP).
     */
    protected function errorResponse(\Throwable $e, string $fallbackMessage = 'Erro interno do servidor'): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->jsonError($e->getMessage(), 422, $e->getErrors());
        }

        // Ex.: falhas da IA, que já trazem status e mensagem para o usuário (502/503/429...)
        if ($e instanceof ErroComStatus) {
            return $this->jsonError($e->getMessage(), $e->status());
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->jsonError('Registro não encontrado', 404);
        }

        if ($e instanceof HttpException) {
            return $this->jsonError($e->getMessage() ?: $fallbackMessage, $e->getStatusCode());
        }

        $status = $e instanceof \InvalidArgumentException ? 400 : $this->httpStatusFrom($e);

        if ($status >= 500) {
            Log::error(sprintf('[%s] %s: %s', static::class, $e::class, $e->getMessage()), ['exception' => $e]);
            report($e);

            return $this->jsonError($fallbackMessage, 500);
        }

        return $this->jsonError($e->getMessage(), $status);
    }

    /**
     * Converte o código de uma exceção em status HTTP válido (PDOException usa SQLSTATE string).
     */
    protected function httpStatusFrom(\Throwable $e, int $default = 500): int
    {
        $code = $e->getCode();

        return is_int($code) && $code >= 400 && $code < 600 ? $code : $default;
    }
}

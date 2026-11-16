<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\PermissionService;
use Cake\Controller\Controller;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\ServerRequest;

class AppController extends Controller
{
    private ?bool $isAdminCache = null;

    /**
     * Os controllers são criados pelo container (Application::services), que resolve os serviços
     * pedidos no construtor. Subclasses com dependências próprias repassam estes dois ao pai.
     */
    public function __construct(ServerRequest $request, protected PermissionService $permissionService)
    {
        parent::__construct($request);
    }

    public function initialize(): void
    {
        parent::initialize();

        $this->response = $this->response->withType('application/json');
        $this->autoRender = false;
    }

    /**
     * ID do usuário autenticado, definido pelo JwtAuthMiddleware.
     */
    protected function currentUserId(): int
    {
        $userId = $this->request->getAttribute('user_id');
        if (!$userId) {
            throw new UnauthorizedException('Usuário não autenticado');
        }

        return (int)$userId;
    }

    /**
     * Role consultada no banco (não no token), para refletir rebaixamentos na hora.
     */
    protected function isAdmin(): bool
    {
        if ($this->isAdminCache === null) {
            $this->isAdminCache = $this->permissionService->isAdmin($this->currentUserId());
        }

        return $this->isAdminCache;
    }

    /**
     * Dono do recurso ou admin.
     */
    protected function canAccessUser(int $usuarioId): bool
    {
        return $usuarioId === $this->currentUserId() || $this->isAdmin();
    }

    /**
     * Corpo da requisição já decodificado pelo BodyParserMiddleware.
     */
    protected function getRequestData(): array
    {
        $data = $this->request->getData();

        return is_array($data) ? $data : [];
    }

    /**
     * Converte o código de uma exceção em status HTTP válido (PDOException usa SQLSTATE string).
     */
    protected function httpStatusFrom(\Throwable $e, int $default = 500): int
    {
        $code = $e->getCode();

        return is_int($code) && $code >= 400 && $code < 600 ? $code : $default;
    }

    protected function jsonResponse($data, $status = 200)
    {
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    protected function jsonSuccess($data = null, string $message = 'success', int $status = 200)
    {
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]));
        return $this->response;
    }

    protected function jsonError(string $message, int $status = 400, array $errors = [])
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors) {
            $body['errors'] = $errors;
        }

        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode($body));
        return $this->response;
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\PermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Restringe o escopo /admin a usuários com role admin no banco (não confia na role do token).
 * Deve rodar depois do JwtAuthMiddleware, que define o atributo user_id.
 */
class AdminMiddleware implements MiddlewareInterface
{
    use JsonErrorResponseTrait;

    public function __construct(private PermissionService $permissionService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $isAdminScope = $path === '/admin' || str_starts_with($path, '/admin/');

        if (!$isAdminScope || $request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $userId = (int)$request->getAttribute('user_id');
        if (!$userId) {
            return $this->jsonErrorResponse(401, 'Usuário não autenticado');
        }

        if (!$this->permissionService->isAdmin($userId)) {
            return $this->jsonErrorResponse(403, 'Acesso negado. Área administrativa.');
        }

        return $handler->handle($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o grupo /admin a usuários com role admin no banco (não confia na role do token).
 * Roda depois do AutenticaJwt, que define o atributo user_id.
 */
class ApenasAdmin
{
    public function __construct(private PermissionService $permissionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $userId = (int) $request->attributes->get('user_id');
        if (! $userId) {
            return RespostaDeErro::json(401, 'Usuário não autenticado');
        }

        if (! $this->permissionService->isAdmin($userId)) {
            return RespostaDeErro::json(403, 'Acesso negado. Área administrativa.');
        }

        return $next($request);
    }
}

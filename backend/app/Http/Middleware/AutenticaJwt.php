<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um access token válido e define os atributos user_id e role da requisição.
 * Aplicado às rotas protegidas em routes/api.php; as públicas ficam fora do grupo.
 */
class AutenticaJwt
{
    public function __construct(private JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $token = $this->jwtService->getTokenFromRequest($request);
        if (! $token) {
            return RespostaDeErro::json(401, 'Token não informado');
        }

        $payload = $this->jwtService->validateToken($token, JwtService::TYPE_ACCESS);
        if (! $payload) {
            return RespostaDeErro::json(401, 'Token inválido ou expirado');
        }

        $request->attributes->set('user_id', (int) $payload['sub']);
        $request->attributes->set('role', $payload['role'] ?? 'user');

        return $next($request);
    }
}

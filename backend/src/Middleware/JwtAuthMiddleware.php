<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Services\JwtService;

class JwtAuthMiddleware implements MiddlewareInterface
{
    use JsonErrorResponseTrait;

    /**
     * Rotas públicas comparadas por igualdade exata.
     */
    private const PUBLIC_PATHS = [
        '/',
        '/auth/login',
        '/auth/register',
        '/auth/forgot-password',
        '/auth/refresh',
        '/auth/logout',
        '/auth/social/exchange',
        '/payments/webhook',
    ];

    /**
     * Rotas públicas comparadas por prefixo (sempre terminado em "/").
     */
    private const PUBLIC_PREFIXES = [
        '/auth/reset-password/',
        '/auth/login/',
        '/social-auth/callback/',
    ];

    /**
     * Rotas públicas apenas para GET (igualdade exata).
     */
    private const PUBLIC_GET_PATHS = [
        '/planos',
    ];

    public function __construct(private JwtService $jwtService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS' || $this->isPublicRoute($request)) {
            return $handler->handle($request);
        }

        $token = $this->jwtService->getTokenFromRequest($request);
        if (!$token) {
            return $this->jsonErrorResponse(401, 'Token não informado');
        }

        $payload = $this->jwtService->validateToken($token, JwtService::TYPE_ACCESS);
        if (!$payload) {
            return $this->jsonErrorResponse(401, 'Token inválido ou expirado');
        }

        $request = $request
            ->withAttribute('user_id', (int)$payload['sub'])
            ->withAttribute('role', $payload['role'] ?? 'user');

        return $handler->handle($request);
    }

    private function isPublicRoute(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return true;
        }

        if ($request->getMethod() === 'GET' && in_array($path, self::PUBLIC_GET_PATHS, true)) {
            return true;
        }

        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

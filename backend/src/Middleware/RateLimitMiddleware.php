<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cache\Contador;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Limite por IP em janela fixa. Rotas de autenticação têm um limite próprio, bem mais baixo.
 * Usa o REMOTE_ADDR (não confia em X-Forwarded-For) e o cache "rate_limit".
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    use JsonErrorResponseTrait;

    private const CACHE_CONFIG = 'rate_limit';

    private const AUTH_PATHS = [
        '/auth/login',
        '/auth/register',
        '/auth/forgot-password',
        '/auth/refresh',
        '/auth/social/exchange',
    ];

    private const EXCLUDED_PATHS = ['/', '/favicon.ico'];

    public function __construct(
        private int $maxRequests,
        private int $maxAuthRequests,
        private int $timeWindow
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($request->getMethod() === 'OPTIONS' || in_array($path, self::EXCLUDED_PATHS, true)) {
            return $handler->handle($request);
        }

        $isAuth = in_array($path, self::AUTH_PATHS, true);
        $limit = $isAuth ? $this->maxAuthRequests : $this->maxRequests;

        $window = intdiv(time(), $this->timeWindow);
        $resetTime = ($window + 1) * $this->timeWindow;
        $key = sprintf('%s_%s_%d', $isAuth ? 'auth' : 'api', md5($this->clientIp($request)), $window);

        $count = Contador::incrementar($key, self::CACHE_CONFIG);

        if ($count > $limit) {
            $retryAfter = max(1, $resetTime - time());

            return $this->jsonErrorResponse(
                429,
                'Muitas requisições. Por favor, aguarde ' . $retryAfter . ' segundos.',
                ['retry_after' => $retryAfter]
            )
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withHeader('X-RateLimit-Limit', (string)$limit)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit', (string)$limit)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $limit - $count))
            ->withHeader('X-RateLimit-Reset', (string)$resetTime);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        return (string)($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    }
}

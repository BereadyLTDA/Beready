<?php

namespace App\Http\Middleware;

use App\Support\Contador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite por IP em janela fixa. Rotas de autenticação têm um limite próprio, bem mais baixo.
 * Usa o IP da conexão (não confia em X-Forwarded-For sem proxy configurado) e o cache padrão.
 */
class LimitaRequisicoes
{
    private const AUTH_PATHS = [
        'auth/login',
        'auth/register',
        'auth/forgot-password',
        'auth/refresh',
        'auth/social/exchange',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS') || $request->path() === '/') {
            return $next($request);
        }

        $isAuth = in_array($request->path(), self::AUTH_PATHS, true);
        $limit = (int) config($isAuth ? 'beready.rate_limit.autenticacao' : 'beready.rate_limit.geral');
        $janela = (int) config('beready.rate_limit.janela_segundos');

        $window = intdiv(time(), $janela);
        $resetTime = ($window + 1) * $janela;
        $key = sprintf('rl_%s_%s_%d', $isAuth ? 'auth' : 'api', md5((string) $request->ip()), $window);

        $count = Contador::incrementar($key, $janela * 2);

        if ($count > $limit) {
            $retryAfter = max(1, $resetTime - time());

            return RespostaDeErro::json(
                429,
                'Muitas requisições. Por favor, aguarde '.$retryAfter.' segundos.',
                ['retry_after' => $retryAfter],
            )
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $limit)
                ->header('X-RateLimit-Remaining', '0');
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $limit - $count));
        $response->headers->set('X-RateLimit-Reset', (string) $resetTime);

        return $response;
    }
}

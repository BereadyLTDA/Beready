<?php

use App\Exceptions\ValidationException;
use App\Http\Middleware\ApenasAdmin;
use App\Http\Middleware\AutenticaJwt;
use App\Http\Middleware\LimitaRequisicoes;
use App\Http\Middleware\LimiteDoPlano;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // API pura, sem prefixo /api: as URLs são as mesmas da versão em CakePHP
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [LimitaRequisicoes::class]);

        $middleware->alias([
            'jwt' => AutenticaJwt::class,
            'admin' => ApenasAdmin::class,
            'limite.plano' => LimiteDoPlano::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Só os erros 5xx vão para o Sentry (quando SENTRY_LARAVEL_DSN/SENTRY_DSN estiver definido)
        Integration::handles($exceptions);

        $exceptions->dontReport([ValidationException::class]);

        // Toda resposta de erro em JSON, no formato { success: false, message, errors? }
        $exceptions->render(function (Throwable $e, Request $request) {
            $debug = (bool) config('app.debug');

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [422, $e->getMessage(), $e->getErrors()],
                $e instanceof LaravelValidationException => [422, $e->getMessage(), $e->errors()],
                $e instanceof ModelNotFoundException => [404, 'Registro não encontrado', []],
                // Método não aceito cai no mesmo 404 de rota inexistente, como no CakePHP
                $e instanceof MethodNotAllowedHttpException,
                $e instanceof NotFoundHttpException => [404, 'Rota não encontrada', []],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage(), []],
                default => [500, $e->getMessage(), []],
            };

            $body = [
                'success' => false,
                'message' => $status >= 500 && ! $debug ? 'Erro interno do servidor' : $message,
            ];
            if ($errors) {
                $body['errors'] = $errors;
            }
            if ($debug) {
                $body['debug'] = ['file' => $e->getFile(), 'line' => $e->getLine(), 'type' => $e::class];
            }

            return response()->json($body, $status);
        });
    })->create();

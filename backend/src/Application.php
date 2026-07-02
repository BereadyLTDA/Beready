<?php
declare(strict_types=1);

namespace App;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\JwtAuthMiddleware;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Response;

// Exceptions custom
use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\WeakPasswordException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\FlashcardNotFoundException;
use App\Exceptions\QuizNotFoundException;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // 1. CORS
            ->add(new CorsMiddleware())

            // 2. Rate Limit
            ->add(new RateLimitMiddleware(100, 60))

            // 3. JWT
            ->add(new JwtAuthMiddleware())

            // 4. Error Handler (captura exceções)
            ->add(new ErrorHandlerMiddleware([
                'exceptionRenderer' => function ($exception, $request) {

                    \Sentry\captureException($exception);
                    \Sentry\flush(2000);

                    $status = 500;
                    $message = $exception->getMessage();

                    if ($exception instanceof EmailAlreadyExistsException) $status = 409;
                    elseif ($exception instanceof WeakPasswordException) $status = 400;
                    elseif ($exception instanceof InvalidTokenException) $status = 400;
                    elseif ($exception instanceof UserNotFoundException) $status = 404;
                    elseif ($exception instanceof FlashcardNotFoundException) $status = 404;
                    elseif ($exception instanceof QuizNotFoundException) $status = 404;
                    elseif ($exception instanceof \InvalidArgumentException) $status = 400;
                    elseif ($exception instanceof \RuntimeException && $exception->getCode() === 404) $status = 404;

                    return new Response([
                        'status' => $status,
                        'type' => 'application/json',
                        'body' => json_encode([
                            'success' => false,
                            'message' => $message
                        ])
                    ]);
                }
            ]))

            // 5. Body Parser
            ->add(new \Cake\Http\Middleware\BodyParserMiddleware())

            // 6. Router
            ->add(new \Cake\Routing\Middleware\RoutingMiddleware($this))

            // 7. Asset
            ->add(new \Cake\Routing\Middleware\AssetMiddleware());

        return $middlewareQueue;
    }

    public function routes(RouteBuilder $routes): void
    {
        require CONFIG . 'routes.php';
    }
}
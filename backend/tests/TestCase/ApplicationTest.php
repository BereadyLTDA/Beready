<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use App\Application;
use App\Middleware\AdminMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\PlanoLimiteMiddleware;
use App\Middleware\RateLimitMiddleware;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\TestCase;

class ApplicationTest extends TestCase
{
    public function testBootstrap(): void
    {
        Configure::write('debug', false);
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('Bake'));
        $this->assertFalse($plugins->has('DebugKit'));
        $this->assertTrue($plugins->has('Migrations'));
    }

    /**
     * Ordem importa: CORS antes de tudo (erros também levam os headers) e o
     * AdminMiddleware e PlanoLimiteMiddleware depois do JwtAuthMiddleware (precisam do user_id).
     */
    public function testMiddleware(): void
    {
        $app = new Application(dirname(__DIR__, 2) . '/config');
        // Jwt, Admin e PlanoLimite entram pelo nome da classe: é o container que os instancia
        $middleware = $app->middleware(new MiddlewareQueue([], $app->getContainer()));

        $expected = [
            CorsMiddleware::class,
            ErrorHandlerMiddleware::class,
            BodyParserMiddleware::class,
            RateLimitMiddleware::class,
            JwtAuthMiddleware::class,
            AdminMiddleware::class,
            PlanoLimiteMiddleware::class,
            RoutingMiddleware::class,
            AssetMiddleware::class,
        ];

        $this->assertCount(count($expected), $middleware);
        foreach ($expected as $position => $class) {
            $middleware->seek($position);
            $this->assertInstanceOf($class, $middleware->current(), "Posição {$position}");
        }
    }
}

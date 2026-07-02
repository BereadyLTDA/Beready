<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Cake\Http\Response;
use App\Services\JwtService;

class JwtAuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    
    private array $publicRoutes = [
        '/', '/health', '/auth/login', '/auth/register', 
        '/auth/forgot-password', '/auth/reset-password',
        '/auth/refresh'
    ];
    
    public function __construct()
    {
        $this->jwtService = new JwtService();
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        
        // Rotas públicas
        if ($this->isPublicRoute($path)) {
            return $handler->handle($request);
        }
        
        $token = $this->jwtService->getTokenFromRequest($request);
        
        if (!$token) {
            error_log("JWT: Token não informado para rota {$path}");
            return $this->unauthorizedResponse('Token não informado');
        }
        
        $payload = $this->jwtService->validateToken($token);
        
        if (!$payload) {
            error_log("JWT: Token inválido ou expirado para rota {$path}");
            return $this->unauthorizedResponse('Token inválido ou expirado');
        }
        
        if ($payload['type'] !== 'access') {
            error_log("JWT: Tipo de token inválido: {$payload['type']}");
            return $this->unauthorizedResponse('Tipo de token inválido');
        }
        
        // EXTRAI A ROLE DO TOKEN
        $role = $payload['role'] ?? 'user';
        
        error_log("=== JWT AUTH: Token válido para usuário {$payload['sub']} com role: {$role}");
        
        // ADICIONA OS ATRIBUTOS NA REQUISIÇÃO
        $request = $request->withAttribute('user_id', $payload['sub'])
                           ->withAttribute('user_email', $payload['email'])
                           ->withAttribute('role', $role);
        
        // VERIFICA SE O ATRIBUTO FOI ADICIONADO (LOG)
        error_log("Atributo 'role' adicionado: " . ($request->getAttribute('role') ?? 'NÃO ADICIONADO'));
        
        return $handler->handle($request);
    }
    
    private function isPublicRoute(string $path): bool
    {
        foreach ($this->publicRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }
        return false;
    }
    
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        return (new Response([
            'status' => 401,
            'type' => 'application/json',
            'body' => json_encode(['success' => false, 'message' => $message])
        ]))->withHeader('Content-Type', 'application/json');
    }
}
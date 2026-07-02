<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Cake\Http\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests = 100;
    private int $timeWindow = 60;
    private string $cachePath;
    
    public function __construct(int $maxRequests = 100, int $timeWindow = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->cachePath = TMP . 'rate_limit' . DS;
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    // ServerRequestInterface
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Rotas que não devem ter rate limit
        $excludedPaths = ['/health', '/', '/favicon.ico'];
        $currentPath = $request->getUri()->getPath();
        
        if (in_array($currentPath, $excludedPaths)) {
            return $handler->handle($request);
        }
        
        $clientId = $this->getClientIdentifier($request);
        $rateLimitData = $this->getRateLimitData($clientId);
        
        if ($rateLimitData['count'] >= $this->maxRequests) {
            $retryAfter = $rateLimitData['reset_time'] - time();
            return $this->createRateLimitResponse($retryAfter);
        }
        
        $this->incrementRateLimit($clientId, $rateLimitData);
        $response = $handler->handle($request);
        
        return $this->addRateLimitHeaders($response, $rateLimitData);
    }
    
    private function getClientIdentifier(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        $ip = $serverParams['HTTP_X_FORWARDED_FOR'] ?? 
              $serverParams['HTTP_CLIENT_IP'] ?? 
              $serverParams['REMOTE_ADDR'] ?? 
              'unknown';
        
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        
        $isSensitive = preg_match('/\/(login|register|forgot-password|reset-password)/', $path);
        
        if ($isSensitive) {
            return md5($ip . $path . $method . '_sensitive');
        }
        
        return md5($ip . $path . $method);
    }
    
    private function getRateLimitData(string $clientId): array
    {
        $cacheFile = $this->cachePath . $clientId . '.json';
        $now = time();
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data['reset_time'] > $now) {
                return $data;
            }
        }
        
        return [
            'count' => 0,
            'reset_time' => $now + $this->timeWindow,
            'limit' => $this->maxRequests
        ];
    }
    
    private function incrementRateLimit(string $clientId, array &$data): void
    {
        $data['count']++;
        $cacheFile = $this->cachePath . $clientId . '.json';
        file_put_contents($cacheFile, json_encode($data));
    }
    
    private function addRateLimitHeaders(ResponseInterface $response, array $data): ResponseInterface
    {
        $remaining = max(0, $this->maxRequests - $data['count']);
        $resetTime = $data['reset_time'];
        
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset', (string)$resetTime);
    }
    
    private function createRateLimitResponse(int $retryAfter): ResponseInterface
    {
        $response = new Response([
            'status' => 429,
            'type' => 'application/json',
            'body' => json_encode([
                'success' => false,
                'message' => 'Muitas requisições. Por favor, aguarde ' . $retryAfter . ' segundos.',
                'retry_after' => $retryAfter
            ])
        ]);
        
        return $response
            ->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', '0');
    }
}
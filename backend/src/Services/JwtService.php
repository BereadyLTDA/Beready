<?php
declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Cake\Core\Configure;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $expires;
    private int $refreshExpires;
    
    public function __construct()
    {
        $this->secret = Configure::read('Jwt.secret');
        $this->algorithm = Configure::read('Jwt.algorithm');
        $this->expires = Configure::read('Jwt.expires');
        $this->refreshExpires = Configure::read('Jwt.refresh_expires');
    }
    
    public function generateTokens(array $user): array
    {
        $issuedAt = time();
        
        $accessToken = JWT::encode(
             [
                'sub' => $user['id'],
                'email' => $user['email'],
                'nome' => $user['nome'],
                'role' => $user['role'] ?? 'user',
                'iat' => $issuedAt,
                'exp' => $issuedAt + $this->expires,
                'type' => 'access'
            ],
            $this->secret,
            $this->algorithm
        );
        
        $refreshToken = JWT::encode(
            [
                'sub' => $user['id'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + $this->refreshExpires,
                'type' => 'refresh'
            ],
            $this->secret,
            $this->algorithm
        );
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->expires,
            'token_type' => 'Bearer'
        ];
    }
    
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            error_log("JWT Validation Error: " . $e->getMessage());
            return null;
        }
    }
    
    public function refreshAccessToken(string $refreshToken): ?array
    {
        $payload = $this->validateToken($refreshToken);
        
        if (!$payload || $payload['type'] !== 'refresh') {
            return null;
        }
        
        $userService = new \App\Services\UserService(new \App\Repositories\UserRepository());
        try {
            $user = $userService->getUserById($payload['sub']);
            
            $issuedAt = time();
            $newAccessToken = JWT::encode(
                [
                    'sub' => $user['id'],
                    'email' => $user['email'],
                    'nome' => $user['nome'],
                    'role' => $user['role'] ?? 'user',
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + $this->expires,
                    'type' => 'access'
                ],
                $this->secret,
                $this->algorithm
            );
            
            return [
                'access_token' => $newAccessToken,
                'expires_in' => $this->expires,
                'token_type' => 'Bearer'
            ];
        } catch (\Exception $e) {
            error_log("Refresh token error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getTokenFromRequest($request): ?string
  {
      $authHeader = $request->getHeaderLine('Authorization');
      error_log("JWT Service: Authorization header: " . ($authHeader ?: 'VAZIO'));
      
      if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
          error_log("JWT Service: Token encontrado via Bearer");
          return $matches[1];
      }
      
      $token = $request->getQuery('token');
      if ($token) {
          error_log("JWT Service: Token encontrado via query param");
          return $token;
      }
      
      error_log("JWT Service: Token NÃO encontrado");
      return null;
  }
}
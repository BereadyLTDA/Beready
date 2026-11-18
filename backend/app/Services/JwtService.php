<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

/**
 * Access token (curto, enviado no header Authorization) e refresh token (longo, só em cookie
 * httpOnly). Mesmo formato da versão em CakePHP: tokens emitidos por ela continuam válidos.
 */
class JwtService
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_REFRESH = 'refresh';

    private string $secret;

    private string $algorithm;

    private int $expires;

    private int $refreshExpires;

    public function __construct()
    {
        $this->secret = (string) config('beready.jwt.secret');
        $this->algorithm = (string) config('beready.jwt.algorithm');
        $this->expires = (int) config('beready.jwt.expires');
        $this->refreshExpires = (int) config('beready.jwt.refresh_expires');
    }

    /**
     * Gera o par access + refresh. O refresh token deve ir só para o cookie httpOnly.
     *
     * @param  array{id: int, email: string, nome: string|null, role?: string|null}  $user
     * @return array{access_token: string, expires_in: int, token_type: string, refresh_token: string}
     */
    public function generateTokens(array $user): array
    {
        $issuedAt = time();

        $refreshToken = JWT::encode([
            'sub' => $user['id'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->refreshExpires,
            'type' => self::TYPE_REFRESH,
        ], $this->secret, $this->algorithm);

        return $this->generateAccessToken($user) + ['refresh_token' => $refreshToken];
    }

    /**
     * @param  array{id: int, email: string, nome: string|null, role?: string|null}  $user
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    public function generateAccessToken(array $user): array
    {
        $issuedAt = time();

        $accessToken = JWT::encode([
            'sub' => $user['id'],
            'email' => $user['email'],
            'nome' => $user['nome'],
            'role' => $user['role'] ?? 'user',
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->expires,
            'type' => self::TYPE_ACCESS,
        ], $this->secret, $this->algorithm);

        return [
            'access_token' => $accessToken,
            'expires_in' => $this->expires,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Retorna o payload se a assinatura, a validade e o tipo (access/refresh) conferirem.
     *
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token, string $expectedType): ?array
    {
        try {
            $payload = (array) JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (\Throwable) {
            return null;
        }

        if (($payload['type'] ?? null) !== $expectedType || empty($payload['sub'])) {
            return null;
        }

        return $payload;
    }

    public function getRefreshExpires(): int
    {
        return $this->refreshExpires;
    }

    public function getTokenFromRequest(Request $request): ?string
    {
        if (preg_match('/^Bearer\s+(\S+)$/', (string) $request->header('Authorization'), $matches)) {
            return $matches[1];
        }

        return null;
    }
}

<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Access token (curto, enviado no header Authorization) e refresh token (longo, só em cookie
 * httpOnly). Mesmo formato da versão em CakePHP: tokens emitidos por ela continuam válidos.
 */
class JwtService
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_REFRESH = 'refresh';

    // Chaves do cache de revogação (Redis em produção, para valer em todas as instâncias)
    private const CHAVE_REVOGADOS = 'jwt_revogados_ate_';

    private const CHAVE_JTI = 'jwt_refresh_revogado_';

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
            'iatm' => self::agoraMs(),
            'exp' => $issuedAt + $this->refreshExpires,
            'type' => self::TYPE_REFRESH,
            'jti' => bin2hex(random_bytes(16)),
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
            'iatm' => self::agoraMs(),
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

        // Revogados: todos os tokens do usuário emitidos antes de revogarTodos() (troca de senha,
        // bloqueio, exclusão) e o refresh token entregue no logout
        $revogadosAte = Cache::get(self::CHAVE_REVOGADOS.$payload['sub']);
        $emitidoMs = (int) ($payload['iatm'] ?? ((int) ($payload['iat'] ?? 0) * 1000));
        if ($revogadosAte !== null && $emitidoMs < (int) $revogadosAte) {
            return null;
        }
        if (! empty($payload['jti']) && Cache::has(self::CHAVE_JTI.$payload['jti'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Invalida todos os tokens (access e refresh) já emitidos para o usuário. Tokens emitidos
     * depois desta chamada continuam válidos.
     */
    public function revogarTodos(int $userId): void
    {
        Cache::put(self::CHAVE_REVOGADOS.$userId, self::agoraMs(), $this->refreshExpires);
    }

    /**
     * Invalida um refresh token (logout). Fica na lista só até a data em que ele expiraria.
     */
    public function revogarRefresh(string $token): void
    {
        $payload = $this->validateToken($token, self::TYPE_REFRESH);
        if (! $payload || empty($payload['jti'])) {
            return;
        }
        $restante = max(1, (int) ($payload['exp'] ?? 0) - time());
        Cache::put(self::CHAVE_JTI.$payload['jti'], true, $restante);
    }

    private static function agoraMs(): int
    {
        return (int) floor(microtime(true) * 1000);
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

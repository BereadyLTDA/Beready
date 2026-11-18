<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\WeakPasswordException;
use App\Services\AssinaturaService;
use App\Services\JwtService;
use App\Services\PermissionService;
use App\Services\SocialAuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;

class UsersController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const ROLES = ['user', 'admin'];

    private const STATUSES = [UserService::STATUS_ATIVO, 'inativo', 'bloqueado'];

    public function __construct(
        private UserService $userService,
        private JwtService $jwtService,
        private AssinaturaService $assinaturaService,
        private SocialAuthService $socialAuthService,
        private PermissionService $permissionService,
    ) {}

    // GET /user/permissions — permissões do usuário autenticado (admin recebe todas)
    public function permissions(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->permissionService->getUserPermissions($this->currentUserId()));
        } catch (\Exception $e) {
            Log::error('Erro ao carregar permissões: '.$e->getMessage());

            return $this->jsonError('Erro ao carregar permissões', 500);
        }
    }

    // GET / (healthcheck)
    public function health(): JsonResponse
    {
        return $this->jsonSuccess(['timestamp' => date('Y-m-d H:i:s')], 'API funcionando!');
    }

    // POST /auth/register
    public function register(Request $request): JsonResponse
    {
        try {
            $user = $this->userService->register($this->requestData($request));

            try {
                $this->assinaturaService->ativarPlanoGratuito((int) $user['id']);
            } catch (\Exception $e) {
                Log::error('Erro ao atribuir plano gratuito: '.$e->getMessage());
            }

            return $this->jsonSuccess(['user' => $user], 'Registro realizado com sucesso', 201);
        } catch (EmailAlreadyExistsException|\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 400));
        } catch (\Exception $e) {
            Log::error('Erro no registro: '.$e->getMessage());

            return $this->jsonError('Erro interno ao realizar o registro', 500);
        }
    }

    // POST /auth/login  { email, password, recaptcha_token }
    public function login(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->jsonError('E-mail e senha são obrigatórios', 400);
        }

        $recaptchaToken = $data['recaptcha_token'] ?? null;
        if (! $recaptchaToken) {
            return $this->jsonError('Token de segurança não enviado', 400);
        }

        if (! $this->validateRecaptcha((string) $recaptchaToken)) {
            return $this->jsonError('Falha na verificação de segurança. Tente novamente.', 400);
        }

        try {
            $user = $this->userService->login((string) $data['email'], (string) $data['password']);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 401));
        } catch (\Exception $e) {
            Log::error('Erro no login: '.$e->getMessage());

            return $this->jsonError('Erro interno', 500);
        }

        return $this->authResponse($user, 'Login realizado com sucesso');
    }

    /**
     * POST /auth/social/exchange
     * Troca o código de uso único gerado no callback do login social pelos tokens.
     */
    public function socialExchange(Request $request): JsonResponse
    {
        $code = (string) ($this->requestData($request)['code'] ?? '');
        $userId = $this->socialAuthService->consumeLoginCode($code);

        if (! $userId) {
            return $this->jsonError('Código inválido ou expirado', 400);
        }

        try {
            $user = $this->userService->getUserById($userId);
        } catch (UserNotFoundException) {
            return $this->jsonError('Código inválido ou expirado', 400);
        }

        if (! $this->userService->isActive($user)) {
            return $this->jsonError('Conta inativa ou bloqueada', 403);
        }

        $this->userService->registerLogin($userId);

        return $this->authResponse($user, 'Login realizado com sucesso');
    }

    /**
     * POST /auth/refresh — novo access token a partir do refresh token do cookie httpOnly.
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = (string) $request->cookie(self::REFRESH_COOKIE);
        $payload = $refreshToken !== '' ? $this->jwtService->validateToken($refreshToken, JwtService::TYPE_REFRESH) : null;

        if (! $payload) {
            return $this->jsonError('Refresh token inválido ou expirado', 401)->withCookie($this->refreshCookieExpirado());
        }

        try {
            $user = $this->userService->getUserById((int) $payload['sub']);
        } catch (UserNotFoundException) {
            return $this->jsonError('Refresh token inválido ou expirado', 401)->withCookie($this->refreshCookieExpirado());
        }

        if (! $this->userService->isActive($user)) {
            return $this->jsonError('Conta inativa ou bloqueada', 401)->withCookie($this->refreshCookieExpirado());
        }

        return $this->jsonSuccess(
            $this->jwtService->generateAccessToken($this->dadosDoToken($user)) + ['user' => $user],
            'Token renovado com sucesso',
        );
    }

    // GET /users/me
    public function me(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->userService->getUserById($this->currentUserId()));
        } catch (UserNotFoundException) {
            return $this->jsonError('Usuário não encontrado', 404);
        }
    }

    // POST /auth/logout — o access token expira sozinho; aqui só o cookie de refresh é apagado
    public function logout(): JsonResponse
    {
        return $this->jsonSuccess(null, 'Logout realizado com sucesso')->withCookie($this->refreshCookieExpirado());
    }

    // GET /users/view/{id}
    public function view(string $id): JsonResponse
    {
        $userId = (int) $id;
        if (! $userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        if (! $this->canAccessUser($userId)) {
            return $this->jsonError('Acesso negado', 403);
        }

        try {
            return $this->jsonSuccess(['user' => $this->userService->getUserById($userId)]);
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        }
    }

    // GET /users/{uuid}
    public function viewByUuid(string $uuid): JsonResponse
    {
        try {
            $user = $this->userService->getUserByUuid($uuid);
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        }

        if (! $this->canAccessUser((int) $user['id'])) {
            return $this->jsonError('Acesso negado', 403);
        }

        return $this->jsonSuccess(['user' => $user]);
    }

    /**
     * PUT /users/update/{id}
     * Campos editáveis + senha; role e status só quando quem edita é admin.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $userId = (int) $id;
        if (! $userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        if (! $this->canAccessUser($userId)) {
            return $this->jsonError('Acesso negado', 403);
        }

        $data = $this->requestData($request);
        $editable = array_intersect_key($data, array_flip([...UserService::EDITABLE_FIELDS, 'senha']));
        $protected = [];

        if ($this->isAdmin()) {
            if (isset($data['role'])) {
                if (! in_array($data['role'], self::ROLES, true)) {
                    return $this->jsonError('Role inválida. Use "user" ou "admin"', 400);
                }
                if ($userId === $this->currentUserId() && $data['role'] !== 'admin') {
                    return $this->jsonError('Você não pode rebaixar seu próprio nível de acesso', 403);
                }
                $protected['role'] = $data['role'];
            }

            if (isset($data['status'])) {
                if (! in_array($data['status'], self::STATUSES, true)) {
                    return $this->jsonError('Status inválido. Use: '.implode(', ', self::STATUSES), 400);
                }
                $protected['status'] = $data['status'];
            }
        }

        try {
            $user = $this->userService->updateUser($userId, $editable, $protected);

            return $this->jsonSuccess(['user' => $user], 'Perfil atualizado com sucesso');
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        } catch (EmailAlreadyExistsException|\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 400));
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário: '.$e->getMessage());

            return $this->jsonError('Erro interno ao atualizar o perfil', 500);
        }
    }

    // DELETE /users/delete/{id}
    public function delete(string $id): JsonResponse
    {
        $userId = (int) $id;
        if (! $userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        if (! $this->canAccessUser($userId)) {
            return $this->jsonError('Acesso negado', 403);
        }

        try {
            $user = $this->userService->getUserById($userId);
            if ($user['role'] === 'admin') {
                return $this->jsonError('Não é possível excluir um usuário administrador', 403);
            }

            $this->userService->deleteUser($userId);

            return $this->jsonSuccess(null, 'Conta excluída com sucesso');
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir usuário: '.$e->getMessage());

            return $this->jsonError('Erro interno', 500);
        }
    }

    // POST /auth/forgot-password  { email } — mesma resposta exista ou não a conta
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        if (empty($data['email'])) {
            return $this->jsonError('E-mail é obrigatório', 400);
        }

        try {
            $this->userService->forgotPassword((string) $data['email']);
        } catch (\Exception $e) {
            Log::error('Erro ao processar recuperação de senha: '.$e->getMessage());
        }

        return $this->jsonSuccess(null, 'Se o e-mail existir, você receberá um link de recuperação');
    }

    // POST /auth/reset-password/{token}  { senha }
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        $data = $this->requestData($request);
        $newPassword = $data['senha'] ?? $data['password'] ?? '';

        if (empty($newPassword)) {
            return $this->jsonError('Nova senha é obrigatória', 400);
        }

        try {
            $this->userService->resetPassword($token, (string) $newPassword);

            return $this->jsonSuccess(null, 'Senha redefinida com sucesso');
        } catch (InvalidTokenException|WeakPasswordException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Erro ao redefinir senha: '.$e->getMessage());

            return $this->jsonError('Erro interno ao redefinir senha', 500);
        }
    }

    // Path desconhecido (com token válido)
    public function notFound(): JsonResponse
    {
        return $this->jsonError('Rota não encontrada', 404);
    }

    private function validateRecaptcha(string $token): bool
    {
        $secretKey = config('beready.recaptcha.secret');
        if (empty($secretKey)) {
            return false;
        }

        $response = Http::asForm()->post((string) config('beready.recaptcha.verify_url'), [
            'secret' => $secretKey,
            'response' => $token,
        ]);

        if (! $response->ok() || empty($response->json('success'))) {
            return false;
        }

        $score = $response->json('score');

        return $score === null || $score >= 0.5;
    }

    /**
     * Resposta de login: access token no JSON e refresh token só no cookie httpOnly.
     *
     * @param  array<string, mixed>  $user
     */
    private function authResponse(array $user, string $message): JsonResponse
    {
        $tokens = $this->jwtService->generateTokens($this->dadosDoToken($user));
        $refreshToken = $tokens['refresh_token'];
        unset($tokens['refresh_token']);

        return $this->jsonSuccess(['user' => $user, 'tokens' => $tokens], $message)
            ->withCookie($this->refreshCookie($refreshToken, time() + $this->jwtService->getRefreshExpires()));
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{id: int, email: string, nome: string|null, role: string|null}
     */
    private function dadosDoToken(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'nome' => $user['nome'] ?? null,
            'role' => $user['role'] ?? null,
        ];
    }

    /**
     * Cookie do refresh token: httpOnly, Path=/auth, sem criptografia (é um JWT assinado).
     */
    private function refreshCookie(string $value, int $expira): Cookie
    {
        $config = config('beready.refresh_cookie');

        return Cookie::create(
            self::REFRESH_COOKIE,
            $value,
            $expira,
            '/auth',
            null,
            (bool) $config['secure'],
            true,
            false,
            strtolower((string) $config['samesite']) ?: null,
        );
    }

    private function refreshCookieExpirado(): Cookie
    {
        return $this->refreshCookie('', 1);
    }
}

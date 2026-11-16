<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\WeakPasswordException;
use App\Services\AssinaturaService;
use App\Services\JwtService;
use App\Services\PermissionService;
use App\Services\SocialAuthService;
use App\Services\UserService;
use Cake\Http\Client;
use Cake\Http\Cookie\Cookie;
use Cake\Http\ServerRequest;
use Cake\Log\Log;

class UsersController extends AppController
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const ROLES = ['user', 'admin'];
    private const STATUSES = [UserService::STATUS_ATIVO, 'inativo', 'bloqueado'];

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private UserService $userService,
        private JwtService $jwtService,
        private AssinaturaService $assinaturaService,
        private SocialAuthService $socialAuthService,
    ) {
        parent::__construct($request, $permissionService);
    }

    /**
     * GET /user/permissions
     * Retorna as permissões do usuário autenticado (admin recebe todas)
     */
    public function permissions()
    {
        $this->request->allowMethod(['get']);

        try {
            return $this->jsonSuccess($this->permissionService->getUserPermissions($this->currentUserId()));
        } catch (\Exception $e) {
            Log::error('Erro ao carregar permissões: ' . $e->getMessage());
            return $this->jsonError('Erro ao carregar permissões', 500);
        }
    }

    public function health()
    {
        return $this->jsonSuccess(['timestamp' => date('Y-m-d H:i:s')], 'API funcionando!');
    }

    public function register()
    {
        $data = $this->getRequestData();
        try {
            $user = $this->userService->register($data);

            try {
                $this->assinaturaService->ativarPlanoGratuito((int)$user['id']);
            } catch (\Exception $e) {
                Log::error('Erro ao atribuir plano gratuito: ' . $e->getMessage());
            }

            return $this->jsonSuccess(['user' => $user], 'Registro realizado com sucesso', 201);
        } catch (EmailAlreadyExistsException | WeakPasswordException | \InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 400));
        } catch (\Exception $e) {
            Log::error('Erro no registro: ' . $e->getMessage());
            return $this->jsonError('Erro interno ao realizar o registro', 500);
        }
    }

    public function login()
    {
        $data = $this->getRequestData();

        if (empty($data['email']) || empty($data['password'])) {
            return $this->jsonError('E-mail e senha são obrigatórios', 400);
        }

        $recaptchaToken = $data['recaptcha_token'] ?? null;
        if (!$recaptchaToken) {
            return $this->jsonError('Token de segurança não enviado', 400);
        }

        if (!$this->validateRecaptcha($recaptchaToken)) {
            return $this->jsonError('Falha na verificação de segurança. Tente novamente.', 400);
        }

        try {
            $user = $this->userService->login($data['email'], $data['password']);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 401));
        } catch (\Exception $e) {
            Log::error('Erro no login: ' . $e->getMessage());
            return $this->jsonError('Erro interno', 500);
        }

        return $this->authResponse($user, 'Login realizado com sucesso');
    }

    /**
     * POST /auth/social/exchange
     * Troca o código de uso único gerado no callback do login social pelos tokens.
     */
    public function socialExchange()
    {
        $code = (string)($this->getRequestData()['code'] ?? '');
        $userId = $this->socialAuthService->consumeLoginCode($code);

        if (!$userId) {
            return $this->jsonError('Código inválido ou expirado', 400);
        }

        try {
            $user = $this->userService->getUserById($userId);
        } catch (UserNotFoundException $e) {
            return $this->jsonError('Código inválido ou expirado', 400);
        }

        if (!$this->userService->isActive($user)) {
            return $this->jsonError('Conta inativa ou bloqueada', 403);
        }

        $this->userService->registerLogin($userId);

        return $this->authResponse($user, 'Login realizado com sucesso');
    }

    /**
     * Valida o token do reCAPTCHA v3
     */
    private function validateRecaptcha(string $token): bool
    {
        $secretKey = env('RECAPTCHA_SECRET_KEY');
        if (empty($secretKey)) {
            return false;
        }

        $response = (new Client())->post(env('RECAPTCHA_VERIFY_URL'), [
            'secret' => $secretKey,
            'response' => $token,
        ]);

        if (!$response->isOk()) {
            return false;
        }

        $data = $response->getJson();
        if (empty($data['success'])) {
            return false;
        }

        return !isset($data['score']) || $data['score'] >= 0.5;
    }

    /**
     * POST /auth/refresh
     * Lê o refresh token do cookie httpOnly e devolve um novo access token + usuário.
     */
    public function refresh()
    {
        $refreshToken = (string)$this->request->getCookie(self::REFRESH_COOKIE);
        $payload = $refreshToken !== '' ? $this->jwtService->validateToken($refreshToken, JwtService::TYPE_REFRESH) : null;

        if (!$payload) {
            return $this->jsonError('Refresh token inválido ou expirado', 401)
                ->withExpiredCookie($this->refreshCookie(''));
        }

        try {
            $user = $this->userService->getUserById((int)$payload['sub']);
        } catch (UserNotFoundException $e) {
            return $this->jsonError('Refresh token inválido ou expirado', 401)
                ->withExpiredCookie($this->refreshCookie(''));
        }

        if (!$this->userService->isActive($user)) {
            return $this->jsonError('Conta inativa ou bloqueada', 401)
                ->withExpiredCookie($this->refreshCookie(''));
        }

        return $this->jsonSuccess(
            $this->jwtService->generateAccessToken($user) + ['user' => $user],
            'Token renovado com sucesso'
        );
    }

    public function me()
    {
        try {
            return $this->jsonSuccess($this->userService->getUserById($this->currentUserId()));
        } catch (UserNotFoundException $e) {
            return $this->jsonError('Usuário não encontrado', 404);
        }
    }

    public function logout()
    {
        return $this->jsonSuccess(null, 'Logout realizado com sucesso')
            ->withExpiredCookie($this->refreshCookie(''));
    }

    public function view($id = null)
    {
        $userId = (int)($id ?? $this->request->getParam('id'));
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        if (!$this->canAccessUser($userId)) {
            return $this->jsonError('Acesso negado', 403);
        }

        try {
            return $this->jsonSuccess(['user' => $this->userService->getUserById($userId)]);
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        }
    }

    public function viewByUuid($uuid = null)
    {
        $uuid = (string)($uuid ?? $this->request->getParam('uuid'));

        try {
            $user = $this->userService->getUserByUuid($uuid);
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        }

        if (!$this->canAccessUser((int)$user['id'])) {
            return $this->jsonError('Acesso negado', 403);
        }

        return $this->jsonSuccess(['user' => $user]);
    }

    /**
     * O próprio usuário edita apenas UserService::EDITABLE_FIELDS e a senha; role/status só o admin.
     */
    public function update($id = null)
    {
        $userId = (int)($id ?? $this->request->getParam('id'));
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        if (!$this->canAccessUser($userId)) {
            return $this->jsonError('Acesso negado', 403);
        }

        $data = $this->getRequestData();
        $editable = array_intersect_key($data, array_flip([...UserService::EDITABLE_FIELDS, 'senha']));

        $protected = [];
        if ($this->isAdmin()) {
            if (isset($data['role'])) {
                if (!in_array($data['role'], self::ROLES, true)) {
                    return $this->jsonError('Role inválida. Use "user" ou "admin"', 400);
                }
                if ($userId === $this->currentUserId() && $data['role'] !== 'admin') {
                    return $this->jsonError('Você não pode rebaixar seu próprio nível de acesso', 403);
                }
                $protected['role'] = $data['role'];
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], self::STATUSES, true)) {
                    return $this->jsonError('Status inválido. Use: ' . implode(', ', self::STATUSES), 400);
                }
                $protected['status'] = $data['status'];
            }
        }

        try {
            $user = $this->userService->updateUser($userId, $editable, $protected);
            return $this->jsonSuccess(['user' => $user], 'Perfil atualizado com sucesso');
        } catch (UserNotFoundException $e) {
            return $this->jsonError($e->getMessage(), 404);
        } catch (EmailAlreadyExistsException | WeakPasswordException | \InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), $this->httpStatusFrom($e, 400));
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário: ' . $e->getMessage());
            return $this->jsonError('Erro interno ao atualizar o perfil', 500);
        }
    }

    public function delete($id = null)
    {
        $userId = (int)($id ?? $this->request->getParam('id'));
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        if (!$this->canAccessUser($userId)) {
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
            Log::error('Erro ao excluir usuário: ' . $e->getMessage());
            return $this->jsonError('Erro interno', 500);
        }
    }

    /**
     * Sempre responde 200 para não revelar se o e-mail está cadastrado.
     */
    public function forgotPassword()
    {
        $this->request->allowMethod(['post']);
        $data = $this->getRequestData();
        if (empty($data['email'])) {
            return $this->jsonError('E-mail é obrigatório', 400);
        }

        try {
            $this->userService->forgotPassword((string)$data['email']);
        } catch (\Exception $e) {
            Log::error('Erro ao processar recuperação de senha: ' . $e->getMessage());
        }

        return $this->jsonSuccess(null, 'Se o e-mail existir, você receberá um link de recuperação');
    }

    public function resetPassword($token = null)
    {
        $this->request->allowMethod(['post']);

        if (empty($token)) {
            return $this->jsonError('Token não fornecido', 400);
        }

        $data = $this->getRequestData();
        $newPassword = $data['senha'] ?? $data['password'] ?? '';

        if (empty($newPassword)) {
            return $this->jsonError('Nova senha é obrigatória', 400);
        }

        try {
            $this->userService->resetPassword($token, $newPassword);
            return $this->jsonSuccess(null, 'Senha redefinida com sucesso');
        } catch (InvalidTokenException | WeakPasswordException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Erro ao redefinir senha: ' . $e->getMessage());
            return $this->jsonError('Erro interno ao redefinir senha', 500);
        }
    }

    public function notFound()
    {
        return $this->jsonError('Rota não encontrada', 404);
    }

    /**
     * Resposta de login: access token no JSON e refresh token só no cookie httpOnly.
     */
    private function authResponse(array $user, string $message)
    {
        $tokens = $this->jwtService->generateTokens($user);
        $refreshToken = $tokens['refresh_token'];
        unset($tokens['refresh_token']);

        return $this->jsonSuccess(['user' => $user, 'tokens' => $tokens], $message)
            ->withCookie($this->refreshCookie(
                $refreshToken,
                new \DateTimeImmutable('+' . $this->jwtService->getRefreshExpires() . ' seconds')
            ));
    }

    private function refreshCookie(string $value, ?\DateTimeInterface $expires = null): Cookie
    {
        return Cookie::create(self::REFRESH_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/auth',
            'httponly' => true,
            'secure' => filter_var(env('REFRESH_COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN),
            'samesite' => env('REFRESH_COOKIE_SAMESITE'),
        ]);
    }
}

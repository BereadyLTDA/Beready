<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\PermissionService;
use App\Services\SocialAuthService;
use App\Services\SocialProviderFactory;
use Cake\Http\Cookie\Cookie;
use Cake\Http\ServerRequest;
use Cake\Log\Log;

/**
 * Login social (Google, Facebook, LinkedIn) via OAuth 2.0 authorization code.
 *
 * GET /auth/login/{provider}          -> gera o "state", guarda em cookie httpOnly e redireciona ao provedor
 * GET /social-auth/callback/{provider} -> valida o "state", obtém o perfil e redireciona ao frontend
 *                                        com um código de uso único (trocado em POST /auth/social/exchange)
 */
class SocialAuthController extends AppController
{
    private const STATE_COOKIE = 'oauth_state';
    private const STATE_TTL = 600;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private SocialAuthService $socialAuthService,
        private SocialProviderFactory $providers,
    ) {
        parent::__construct($request, $permissionService);
    }

    public function login(string $provider)
    {
        $state = bin2hex(random_bytes(32));
        // approval_prompt é um parâmetro legado que a biblioteca acrescenta por padrão
        $authUrl = $this->providers->make($provider)
            ->getAuthorizationUrl(['state' => $state, 'approval_prompt' => null]);

        return $this->redirect($authUrl)
            ->withCookie($this->stateCookie($state, new \DateTimeImmutable('+' . self::STATE_TTL . ' seconds')));
    }

    public function callback(string $provider)
    {
        $expectedState = (string)$this->request->getCookie(self::STATE_COOKIE);
        $state = (string)$this->request->getQuery('state');
        $code = (string)$this->request->getQuery('code');

        if ($expectedState === '' || $state === '' || !hash_equals($expectedState, $state) || $code === '') {
            return $this->failureRedirect('state inválido ou código ausente (' . $provider . ')');
        }

        try {
            $userInfo = $this->providers->fetchProfile($provider, $code);
            if (empty($userInfo['email'])) {
                return $this->failureRedirect('perfil sem e-mail (' . $provider . ')');
            }

            $userId = $this->socialAuthService->findOrCreateUser($userInfo);
            $loginCode = $this->socialAuthService->createLoginCode($userId);
        } catch (\Exception $e) {
            return $this->failureRedirect($e->getMessage());
        }

        return $this->redirect(env('APP_BASE_URL') . 'oauth-callback?code=' . $loginCode)
            ->withExpiredCookie($this->stateCookie(''));
    }

    private function failureRedirect(string $reason)
    {
        Log::warning('Falha no login social: ' . $reason);

        return $this->redirect(env('APP_BASE_URL') . 'login?error=social_auth_failed')
            ->withExpiredCookie($this->stateCookie(''));
    }

    private function stateCookie(string $value, ?\DateTimeInterface $expires = null): Cookie
    {
        return Cookie::create(self::STATE_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/social-auth',
            'httponly' => true,
            'secure' => filter_var(env('REFRESH_COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN),
            // Lax é necessário para o cookie voltar no redirecionamento (GET top-level) do provedor
            'samesite' => 'Lax',
        ]);
    }
}

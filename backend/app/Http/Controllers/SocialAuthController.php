<?php

namespace App\Http\Controllers;

use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Login social (Google, Facebook, LinkedIn) via OAuth 2.0 authorization code, com o Socialite.
 *
 * GET /auth/login/{provider}          -> gera o "state", guarda em cookie httpOnly e redireciona ao provedor
 * GET /social-auth/callback/{provider} -> valida o "state", obtém o perfil e redireciona ao frontend
 *                                        com um código de uso único (trocado em POST /auth/social/exchange)
 *
 * A API não tem sessão: o Socialite roda em modo stateless e o "state" é conferido aqui pelo cookie.
 */
class SocialAuthController extends Controller
{
    private const STATE_COOKIE = 'oauth_state';

    private const STATE_TTL = 600;

    /**
     * Nome do provedor na URL => driver do Socialite.
     */
    private const DRIVERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'linkedin' => 'linkedin-openid',
    ];

    private const SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        'facebook' => ['email', 'public_profile'],
        'linkedin' => ['openid', 'profile', 'email'],
    ];

    public function __construct(private SocialAuthService $socialAuthService) {}

    public function login(Request $request, string $provider): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));

        $url = $this->driver($provider, $request)
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return redirect()->away($url)->withCookie($this->stateCookie($state, self::STATE_TTL));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $expectedState = (string) $request->cookie(self::STATE_COOKIE);
        $state = (string) $request->query('state');
        $code = (string) $request->query('code');

        if ($expectedState === '' || $state === '' || ! hash_equals($expectedState, $state) || $code === '') {
            return $this->failureRedirect('state inválido ou código ausente ('.$provider.')');
        }

        try {
            $perfil = $this->driver($provider, $request)->user();
            $userInfo = $this->normalizarPerfil($perfil->getRaw(), $perfil->getEmail(), $perfil->getName(), $perfil->getAvatar());

            if (empty($userInfo['email'])) {
                return $this->failureRedirect('perfil sem e-mail ('.$provider.')');
            }

            $userId = $this->socialAuthService->findOrCreateUser($userInfo);
            $loginCode = $this->socialAuthService->createLoginCode($userId);
        } catch (\Throwable $e) {
            return $this->failureRedirect($e->getMessage());
        }

        return redirect()->away(config('beready.frontend_url').'oauth-callback?code='.$loginCode)
            ->withCookie($this->stateCookie('', -1));
    }

    /**
     * O SocialiteManager guarda a instância do driver; o request atual é passado sempre, porque com
     * o Octane (processo que atende várias requisições) a instância guardada teria um request antigo.
     */
    private function driver(string $provider, Request $request): Provider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver(self::DRIVERS[$provider]);
        $driver->setRequest($request)->stateless()->scopes(self::SCOPES[$provider]);

        // A Graph API só devolve os campos pedidos (a foto inclusive)
        if ($driver instanceof FacebookProvider) {
            $driver->fields(['name', 'email', 'picture']);
        }

        return $driver;
    }

    /**
     * Perfil no formato que o SocialAuthService espera (o mesmo da versão em CakePHP: os campos do
     * provedor, com a foto do Facebook já como URL).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizarPerfil(array $raw, ?string $email, ?string $nome, ?string $avatar): array
    {
        if (isset($raw['picture']['data']['url'])) {
            $raw['picture'] = $raw['picture']['data']['url'];
        }

        return $raw + array_filter(['email' => $email, 'name' => $nome, 'picture' => $avatar]);
    }

    private function failureRedirect(string $reason): RedirectResponse
    {
        Log::warning('Falha no login social: '.$reason);

        return redirect()->away(config('beready.frontend_url').'login?error=social_auth_failed')
            ->withCookie($this->stateCookie('', -1));
    }

    /**
     * Cookie do "state" (Path=/social-auth). Lax é necessário para o cookie voltar no redirecionamento
     * (GET top-level) do provedor. $segundos negativo expira o cookie.
     */
    private function stateCookie(string $value, int $segundos): Cookie
    {
        return Cookie::create(
            self::STATE_COOKIE,
            $value,
            time() + $segundos,
            '/social-auth',
            null,
            (bool) config('beready.refresh_cookie.secure'),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}

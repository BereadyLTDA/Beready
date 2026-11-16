<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;

use function Cake\Core\env;

/**
 * Monta o cliente OAuth 2.0 (authorization code) de cada provedor de login social.
 *
 * Os três provedores seguem o mesmo fluxo; o que muda é só a configuração, lida do .env
 * ({PROVEDOR}_CLIENT_ID, _CLIENT_SECRET, _REDIRECT_URI, _AUTH_URL, _TOKEN_URL, _USERINFO_URL).
 */
class SocialProviderFactory
{
    /**
     * Provedor => [escopos, separador usado na URL de autorização].
     */
    private const ESCOPOS = [
        'google' => [['openid', 'email', 'profile'], ' '],
        'facebook' => [['email', 'public_profile'], ','],
        'linkedin' => [['openid', 'profile', 'email'], ' '],
    ];

    /**
     * @param \GuzzleHttp\ClientInterface|null $httpClient cliente HTTP alternativo (testes)
     */
    public function __construct(private ?ClientInterface $httpClient = null)
    {
    }

    public function make(string $provider): GenericProvider
    {
        if (!isset(self::ESCOPOS[$provider])) {
            throw new \InvalidArgumentException('Provedor de login social desconhecido: ' . $provider);
        }

        [$escopos, $separador] = self::ESCOPOS[$provider];
        $prefix = strtoupper($provider);

        $userInfoUrl = (string)env($prefix . '_USERINFO_URL');
        if ($provider === 'facebook') {
            // A Graph API só devolve os campos pedidos
            $userInfoUrl .= (str_contains($userInfoUrl, '?') ? '&' : '?') . 'fields=id,name,email,picture';
        }

        return new GenericProvider(
            [
                'clientId' => (string)env($prefix . '_CLIENT_ID'),
                'clientSecret' => (string)env($prefix . '_CLIENT_SECRET'),
                'redirectUri' => (string)env($prefix . '_REDIRECT_URI'),
                'urlAuthorize' => (string)env($prefix . '_AUTH_URL'),
                'urlAccessToken' => (string)env($prefix . '_TOKEN_URL'),
                'urlResourceOwnerDetails' => $userInfoUrl,
                'scopes' => $escopos,
                'scopeSeparator' => $separador,
            ],
            $this->httpClient ? ['httpClient' => $this->httpClient] : [],
        );
    }

    /**
     * Troca o código de autorização pelo perfil do usuário (e-mail, nome, foto).
     *
     * @return array<string, mixed>
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException se o provedor recusar
     */
    public function fetchProfile(string $provider, string $code): array
    {
        $oauth = $this->make($provider);
        $token = $oauth->getAccessToken('authorization_code', ['code' => $code]);
        assert($token instanceof AccessToken);
        $perfil = $oauth->getResourceOwner($token)->toArray();

        // O Facebook devolve a foto aninhada em picture.data.url
        if (isset($perfil['picture']['data']['url'])) {
            $perfil['picture'] = $perfil['picture']['data']['url'];
        }

        return $perfil;
    }
}

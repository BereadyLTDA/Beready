<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\SocialProviderFactory;
use App\Test\TestCase\ApiTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Login social: os provedores são simulados por um cliente HTTP falso, então o que se verifica
 * aqui é o que a API envia a eles e o que faz com as respostas.
 */
class SocialAuthTest extends ApiTestCase
{
    /**
     * Requisições que a API fez ao provedor, na ordem.
     *
     * @var array<int, array{request: \Psr\Http\Message\RequestInterface}>
     */
    private array $enviadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withBearer(null);
        $this->setEnv('APP_BASE_URL', 'http://localhost:5173/');

        foreach (['GOOGLE', 'FACEBOOK', 'LINKEDIN'] as $prefix) {
            $host = strtolower($prefix) . '.test';
            $this->setEnv("{$prefix}_CLIENT_ID", "id-{$host}");
            $this->setEnv("{$prefix}_CLIENT_SECRET", "segredo-{$host}");
            $this->setEnv("{$prefix}_REDIRECT_URI", "http://localhost:8765/social-auth/callback/" . strtolower($prefix));
            $this->setEnv("{$prefix}_AUTH_URL", "https://{$host}/authorize");
            $this->setEnv("{$prefix}_TOKEN_URL", "https://{$host}/token");
            $this->setEnv("{$prefix}_USERINFO_URL", "https://{$host}/userinfo");
        }
    }

    /**
     * Faz o provedor responder, em sequência, com os corpos JSON informados.
     *
     * @param array<int, array{0: int, 1: array<string, mixed>}> $respostas [status, corpo]
     */
    private function provedorResponde(array $respostas): void
    {
        $fila = array_map(
            fn (array $r) => new Response($r[0], ['Content-Type' => 'application/json'], (string)json_encode($r[1])),
            $respostas,
        );
        $stack = HandlerStack::create(new MockHandler($fila));
        $stack->push(Middleware::history($this->enviadas));
        $factory = new SocialProviderFactory(new Client(['handler' => $stack]));

        $this->mockService(SocialProviderFactory::class, fn () => $factory);
    }

    /**
     * @return array<string, string>
     */
    private function queryDoRedirect(): array
    {
        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        return $query;
    }

    public function testLoginRedirecionaAoProvedorComStateEmCookie(): void
    {
        $this->get('/auth/login/google');

        $this->assertResponseCode(302);
        $this->assertStringStartsWith('https://google.test/authorize?', $this->_response->getHeaderLine('Location'));

        $query = $this->queryDoRedirect();
        $this->assertSame('id-google.test', $query['client_id']);
        $this->assertSame('http://localhost:8765/social-auth/callback/google', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertArrayNotHasKey('approval_prompt', $query);

        $cookie = $this->_response->getCookieCollection()->get('oauth_state');
        $this->assertSame(64, strlen($cookie->getValue()));
        $this->assertSame($cookie->getValue(), $query['state']);
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testEscoposDoFacebookSaoSeparadosPorVirgula(): void
    {
        $this->get('/auth/login/facebook');

        $this->assertSame('email,public_profile', $this->queryDoRedirect()['scope']);
    }

    public function testProvedorDesconhecidoNaoTemRota(): void
    {
        $this->get('/auth/login/github');

        $this->assertResponseCode(404);
    }

    public function testCallbackCriaUsuarioEDevolveCodigoTrocavelPorTokens(): void
    {
        $this->provedorResponde([
            [200, ['access_token' => 'token-google', 'token_type' => 'Bearer', 'expires_in' => 3600]],
            [200, ['email' => 'social@teste.local', 'given_name' => 'Ana', 'family_name' => 'Souza']],
        ]);

        $this->cookie('oauth_state', 'state-valido');
        $this->get('/social-auth/callback/google?state=state-valido&code=codigo-do-provedor');

        $this->assertResponseCode(302);
        $this->assertStringStartsWith(
            'http://localhost:5173/oauth-callback?code=',
            $this->_response->getHeaderLine('Location'),
        );

        // 1ª requisição: troca do código pelo access token
        $token = $this->enviadas[0]['request'];
        $this->assertSame('POST', $token->getMethod());
        $this->assertSame('https://google.test/token', (string)$token->getUri());
        parse_str((string)$token->getBody(), $corpo);
        $this->assertSame('authorization_code', $corpo['grant_type']);
        $this->assertSame('codigo-do-provedor', $corpo['code']);
        $this->assertSame('id-google.test', $corpo['client_id']);
        $this->assertSame('segredo-google.test', $corpo['client_secret']);
        $this->assertSame('http://localhost:8765/social-auth/callback/google', $corpo['redirect_uri']);

        // 2ª requisição: perfil, autenticada com o access token
        $perfil = $this->enviadas[1]['request'];
        $this->assertSame('GET', $perfil->getMethod());
        $this->assertSame('https://google.test/userinfo', (string)$perfil->getUri());
        $this->assertSame('Bearer token-google', $perfil->getHeaderLine('Authorization'));

        $user = $this->getTableLocator()->get('Users')->find()->where(['email' => 'social@teste.local'])->firstOrFail();
        $this->assertSame('Ana Souza', $user->nome);

        // O código devolvido ao frontend vale uma única troca por tokens
        $codigo = $this->queryDoRedirect()['code'];
        $this->postJson('/auth/social/exchange', ['code' => $codigo]);
        $this->assertResponseOk();
        $this->assertSame('social@teste.local', $this->responseJson()['data']['user']['email']);

        $this->postJson('/auth/social/exchange', ['code' => $codigo]);
        $this->assertResponseCode(400);
    }

    public function testFacebookPedeOsCamposENormalizaAFoto(): void
    {
        $this->provedorResponde([
            [200, ['access_token' => 'token-fb', 'token_type' => 'bearer']],
            [200, [
                'id' => '10',
                'name' => 'Bia Lima',
                'email' => 'bia@teste.local',
                'picture' => ['data' => ['url' => 'https://facebook.test/foto.jpg']],
            ]],
        ]);

        $this->cookie('oauth_state', 'abc');
        $this->get('/social-auth/callback/facebook?state=abc&code=xyz');

        $this->assertSame(
            'https://facebook.test/userinfo?fields=id,name,email,picture',
            urldecode((string)$this->enviadas[1]['request']->getUri()),
        );

        $user = $this->getTableLocator()->get('Users')->find()->where(['email' => 'bia@teste.local'])->firstOrFail();
        $this->assertSame('Bia Lima', $user->nome);
        $this->assertSame('https://facebook.test/foto.jpg', $user->foto_perfil);
    }

    public function testCallbackComStateDiferenteDoCookieNaoChamaOProvedor(): void
    {
        $this->provedorResponde([]);

        $this->cookie('oauth_state', 'esperado');
        $this->get('/social-auth/callback/google?state=outro&code=xyz');

        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');
        $this->assertSame([], $this->enviadas);
    }

    public function testErroDoProvedorOuPerfilSemEmailVoltamParaOLoginComErro(): void
    {
        $this->provedorResponde([
            [400, ['error' => 'invalid_grant', 'error_description' => 'Código expirado']],
        ]);
        $this->cookie('oauth_state', 'abc');
        $this->get('/social-auth/callback/linkedin?state=abc&code=vencido');
        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');

        $this->provedorResponde([
            [200, ['access_token' => 'token-li']],
            [200, ['name' => 'Sem E-mail']],
        ]);
        $this->cookie('oauth_state', 'abc');
        $this->get('/social-auth/callback/linkedin?state=abc&code=ok');
        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');

        $this->assertSame(0, $this->getTableLocator()->get('Users')->find()->count());
    }
}

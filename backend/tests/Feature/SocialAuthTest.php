<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Facades\Socialite;
use Psr\Http\Message\RequestInterface;
use Tests\ApiTestCase;

/**
 * Login social: os provedores são simulados por um cliente HTTP falso injetado no Socialite, então o
 * que se verifica aqui é o que a API envia a eles e o que faz com as respostas.
 *
 * Diferença em relação à versão em CakePHP: as URLs dos provedores são as oficiais do Socialite
 * (antes vinham do .env), por isso as asserções de URL usam os endereços reais.
 */
class SocialAuthTest extends ApiTestCase
{
    /**
     * Requisições que a API fez ao provedor, na ordem.
     *
     * @var array<int, array{request: RequestInterface}>
     */
    private array $enviadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withBearer(null);
        $this->setEnv('APP_BASE_URL', 'http://localhost:5173/');

        foreach (['google' => 'google', 'facebook' => 'facebook', 'linkedin-openid' => 'linkedin'] as $servico => $nome) {
            config(["services.{$servico}" => [
                'client_id' => "id-{$nome}.test",
                'client_secret' => "segredo-{$nome}.test",
                'redirect' => "http://localhost:8765/social-auth/callback/{$nome}",
            ]]);
        }
    }

    /**
     * Faz o provedor responder, em sequência, com os corpos JSON informados.
     *
     * @param  string  $driver  driver do Socialite (google, facebook, linkedin-openid)
     * @param  array<int, array{0: int, 1: array<string, mixed>}>  $respostas  [status, corpo]
     */
    private function provedorResponde(string $driver, array $respostas): void
    {
        $fila = array_map(
            fn (array $r) => new Response($r[0], ['Content-Type' => 'application/json'], (string) json_encode($r[1])),
            $respostas,
        );
        $stack = HandlerStack::create(new MockHandler($fila));
        $stack->push(Middleware::history($this->enviadas));

        // O SocialiteManager guarda a instância do driver: o controller usa este mesmo cliente
        Socialite::driver($driver)->setHttpClient(new Client(['handler' => $stack]));
    }

    /**
     * @return array<string, string>
     */
    private function queryDoRedirect(): array
    {
        parse_str((string) parse_url((string) $this->ultimaResposta->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_login_redireciona_ao_provedor_com_state_em_cookie(): void
    {
        $this->get('/auth/login/google');

        $this->assertResponseCode(302);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $this->ultimaResposta->headers->get('Location'));

        $query = $this->queryDoRedirect();
        $this->assertSame('id-google.test', $query['client_id']);
        $this->assertSame('http://localhost:8765/social-auth/callback/google', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertEqualsCanonicalizing(['openid', 'email', 'profile'], explode(' ', $query['scope']));
        $this->assertArrayNotHasKey('approval_prompt', $query);

        $cookie = $this->ultimaResposta->getCookie('oauth_state', false);
        $this->assertSame(64, strlen((string) $cookie->getValue()));
        $this->assertSame($cookie->getValue(), $query['state']);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/social-auth', $cookie->getPath());
    }

    public function test_escopos_do_facebook_sao_separados_por_virgula(): void
    {
        $this->get('/auth/login/facebook');

        $this->assertSame('email,public_profile', $this->queryDoRedirect()['scope']);
    }

    public function test_provedor_desconhecido_nao_tem_rota(): void
    {
        $this->get('/auth/login/github');

        // Sem token, o path desconhecido para no AutenticaJwt (como no CakePHP)
        $this->assertResponseCode(401);
    }

    public function test_callback_cria_usuario_e_devolve_codigo_trocavel_por_tokens(): void
    {
        $this->provedorResponde('google', [
            [200, ['access_token' => 'token-google', 'token_type' => 'Bearer', 'expires_in' => 3600]],
            [200, ['sub' => '1', 'email' => 'social@teste.local', 'given_name' => 'Ana', 'family_name' => 'Souza']],
        ]);

        $this->cookie('oauth_state', 'state-valido');
        $this->get('/social-auth/callback/google?state=state-valido&code=codigo-do-provedor');

        $this->assertResponseCode(302);
        $this->assertStringStartsWith('http://localhost:5173/oauth-callback?code=', $this->ultimaResposta->headers->get('Location'));

        // 1ª requisição: troca do código pelo access token
        $token = $this->enviadas[0]['request'];
        $this->assertSame('POST', $token->getMethod());
        $this->assertSame('https://www.googleapis.com/oauth2/v4/token', (string) $token->getUri());
        parse_str((string) $token->getBody(), $corpo);
        $this->assertSame('authorization_code', $corpo['grant_type']);
        $this->assertSame('codigo-do-provedor', $corpo['code']);
        $this->assertSame('id-google.test', $corpo['client_id']);
        $this->assertSame('segredo-google.test', $corpo['client_secret']);
        $this->assertSame('http://localhost:8765/social-auth/callback/google', $corpo['redirect_uri']);

        // 2ª requisição: perfil, autenticada com o access token
        $perfil = $this->enviadas[1]['request'];
        $this->assertSame('GET', $perfil->getMethod());
        $this->assertStringStartsWith('https://www.googleapis.com/oauth2/v3/userinfo', (string) $perfil->getUri());
        $this->assertSame('Bearer token-google', $perfil->getHeaderLine('Authorization'));

        $user = User::where('email', 'social@teste.local')->firstOrFail();
        $this->assertSame('Ana Souza', $user->nome);

        // O código devolvido ao frontend vale uma única troca por tokens
        $codigo = $this->queryDoRedirect()['code'];
        $this->postJson('/auth/social/exchange', ['code' => $codigo]);
        $this->assertResponseOk();
        $this->assertSame('social@teste.local', $this->responseJson()['data']['user']['email']);

        $this->postJson('/auth/social/exchange', ['code' => $codigo]);
        $this->assertResponseCode(400);
    }

    public function test_facebook_pede_os_campos_e_normaliza_a_foto(): void
    {
        $this->provedorResponde('facebook', [
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

        parse_str((string) parse_url((string) $this->enviadas[1]['request']->getUri(), PHP_URL_QUERY), $query);
        $this->assertSame('name,email,picture', $query['fields']);

        $user = User::where('email', 'bia@teste.local')->firstOrFail();
        $this->assertSame('Bia Lima', $user->nome);
        $this->assertSame('https://facebook.test/foto.jpg', $user->foto_perfil);
    }

    public function test_callback_com_state_diferente_do_cookie_nao_chama_o_provedor(): void
    {
        $this->provedorResponde('google', []);

        $this->cookie('oauth_state', 'esperado');
        $this->get('/social-auth/callback/google?state=outro&code=xyz');

        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');
        $this->assertSame([], $this->enviadas);
    }

    public function test_erro_do_provedor_ou_perfil_sem_email_voltam_para_o_login_com_erro(): void
    {
        $this->provedorResponde('linkedin-openid', [
            [400, ['error' => 'invalid_grant', 'error_description' => 'Código expirado']],
        ]);
        $this->cookie('oauth_state', 'abc');
        $this->get('/social-auth/callback/linkedin?state=abc&code=vencido');
        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');

        $this->provedorResponde('linkedin-openid', [
            [200, ['access_token' => 'token-li']],
            [200, ['sub' => '7', 'name' => 'Sem E-mail']],
        ]);
        $this->cookie('oauth_state', 'abc');
        $this->get('/social-auth/callback/linkedin?state=abc&code=ok');
        $this->assertRedirect('http://localhost:5173/login?error=social_auth_failed');

        $this->assertSame(0, User::count());
    }
}

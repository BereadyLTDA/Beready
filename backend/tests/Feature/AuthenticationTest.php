<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Firebase\JWT\JWT;
use Tests\ApiTestCase;

/**
 * JwtAuthMiddleware, rotas públicas e o ciclo refresh/logout do UsersController.
 */
class AuthenticationTest extends ApiTestCase
{
    public function test_rota_protegida_sem_token_retorna401(): void
    {
        $this->withBearer(null);

        foreach (['/users/me', '/flashcards', '/user/assinatura', '/user/permissions'] as $url) {
            $this->get($url);
            $this->assertResponseCode(401, $url);
            $this->assertFalse($this->responseJson()['success']);
        }
    }

    public function test_token_malformado_retorna401(): void
    {
        $this->withBearer('isto.nao.e-um-jwt');
        $this->get('/users/me');

        $this->assertResponseCode(401);
    }

    public function test_token_assinado_com_outro_segredo_retorna401(): void
    {
        $user = $this->createUser();
        $token = JWT::encode(
            ['sub' => $user->id, 'type' => JwtService::TYPE_ACCESS, 'iat' => time(), 'exp' => time() + 3600],
            'segredo-de-outro-sistema-com-32-bytes-ou-mais',
            config('beready.jwt.algorithm')
        );

        $this->withBearer($token);
        $this->get('/users/me');

        $this->assertResponseCode(401);
    }

    public function test_token_expirado_retorna401(): void
    {
        $user = $this->createUser();
        $token = JWT::encode(
            ['sub' => $user->id, 'type' => JwtService::TYPE_ACCESS, 'iat' => time() - 7200, 'exp' => time() - 3600],
            config('beready.jwt.secret'),
            config('beready.jwt.algorithm')
        );

        $this->withBearer($token);
        $this->get('/users/me');

        $this->assertResponseCode(401);
    }

    public function test_refresh_token_nao_serve_como_access_token(): void
    {
        $user = $this->createUser();

        $this->withBearer($this->tokensFor($user)['refresh_token']);
        $this->get('/users/me');

        $this->assertResponseCode(401);
    }

    public function test_access_token_valido_acessa_rota_protegida(): void
    {
        $user = $this->createUser();

        $this->actingAs($user);
        $this->get('/users/me');

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertSame($user->id, $body['data']['id']);
        $this->assertArrayNotHasKey('senha_hash', $body['data']);
    }

    public function test_listagem_de_planos_eh_publica(): void
    {
        $this->withBearer(null);
        $this->get('/planos');

        $this->assertResponseOk();
        $nomes = array_column($this->responseJson()['data'], 'nome');
        $this->assertSame(['Gratuito', 'Trial', 'Premium'], $nomes);
    }

    public function test_apenas_get_de_planos_eh_publico(): void
    {
        $this->withBearer(null);
        $this->postJson('/planos/'.self::PLANO_GRATUITO.'/assinar', ['ciclo' => 'mensal']);

        $this->assertResponseCode(401);
    }

    public function test_refresh_sem_cookie_retorna401(): void
    {
        $this->withBearer(null);
        $this->post('/auth/refresh');

        $this->assertResponseCode(401);
    }

    public function test_refresh_com_cookie_valido_retorna_novo_access_token(): void
    {
        $user = $this->createUser();

        $this->withBearer(null);
        $this->cookie('refresh_token', $this->tokensFor($user)['refresh_token']);
        $this->post('/auth/refresh');

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertNotEmpty($data['access_token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertArrayNotHasKey('senha_hash', $data['user']);

        // O novo access token funciona nas rotas protegidas
        $payload = (new JwtService)->validateToken($data['access_token'], JwtService::TYPE_ACCESS);
        $this->assertSame($user->id, (int) $payload['sub']);
    }

    public function test_refresh_com_access_token_no_cookie_retorna401(): void
    {
        $user = $this->createUser();

        $this->withBearer(null);
        $this->cookie('refresh_token', $this->tokensFor($user)['access_token']);
        $this->post('/auth/refresh');

        $this->assertResponseCode(401);
    }

    public function test_refresh_de_usuario_bloqueado_retorna401(): void
    {
        $user = $this->createUser('user', ['status' => 'bloqueado']);

        $this->withBearer(null);
        $this->cookie('refresh_token', $this->tokensFor($user)['refresh_token']);
        $this->post('/auth/refresh');

        $this->assertResponseCode(401);
    }

    public function test_logout_expira_o_cookie_de_refresh(): void
    {
        $this->withBearer(null);
        $this->post('/auth/logout');

        $this->assertResponseOk();
        $cookie = $this->ultimaResposta->getCookie('refresh_token', false);
        $this->assertSame('', $cookie->getValue());
        $this->assertTrue($cookie->isCleared());
        $this->assertSame('/auth', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_social_exchange_com_codigo_invalido_retorna400(): void
    {
        $this->withBearer(null);

        // Formato inválido e formato válido mas inexistente
        foreach (['abc', str_repeat('a', 64)] as $code) {
            $this->postJson('/auth/social/exchange', ['code' => $code]);
            $this->assertResponseCode(400);
        }
    }

    public function test_registro_grava_senha_ignora_campos_protegidos_e_atribui_plano_gratuito(): void
    {
        $this->withBearer(null);
        $this->recaptchaAprova();
        $this->postJson('/auth/register', [
            'recaptcha_token' => 'token-de-teste',
            'aceite_termos' => true,
            'nome' => 'Novo Usuário',
            'email' => 'novo@teste.local',
            'senha' => 'senhaForte1',
            'role' => 'admin',
            'status' => 'bloqueado',
            'senha_hash' => 'hash-forjado',
        ]);

        $this->assertResponseCode(201);
        $id = $this->responseJson()['data']['user']['id'];
        $this->assertArrayNotHasKey('senha_hash', $this->responseJson()['data']['user']);

        $user = $this->fetch('Users', $id);
        $this->assertSame('user', $user->role);
        $this->assertSame('ativo', $user->status);
        $this->assertTrue(password_verify('senhaForte1', $user->senha_hash));
        $this->assertNotEmpty($user->uuid);

        $assinatura = $this->tabela('Assinaturas')
            ->where(['usuario_id' => $id, 'status' => 'active'])
            ->first();
        $this->assertSame(self::PLANO_GRATUITO, $assinatura->plano_id);

        $this->assertMailSentTo('novo@teste.local');
    }

    public function test_registro_com_email_duplicado_retorna409(): void
    {
        $existente = $this->createUser();

        $this->withBearer(null);
        $this->recaptchaAprova();
        $this->postJson('/auth/register', ['recaptcha_token' => 'token-de-teste', 'aceite_termos' => true, 'nome' => 'Outro', 'email' => $existente->email, 'senha' => 'senhaForte1']);

        $this->assertResponseCode(409);
    }

    public function test_rate_limit_das_rotas_de_autenticacao(): void
    {
        $this->withBearer(null);

        for ($i = 1; $i <= 10; $i++) {
            $this->post('/auth/refresh');
            $this->assertResponseCode(401);
        }

        $this->post('/auth/refresh');
        $this->assertResponseCode(429);
        $this->assertHeader('X-RateLimit-Remaining', '0');
    }
}

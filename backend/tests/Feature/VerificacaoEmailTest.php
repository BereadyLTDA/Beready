<?php

namespace Tests\Feature;

use App\Mail\BoasVindas;
use App\Mail\VerificarEmail;
use App\Models\User;
use App\Services\JwtService;
use App\Services\SocialAuthService;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

/**
 * Cadastro com confirmação de e-mail: só entra quem clicou no link enviado ao endereço.
 */
class VerificacaoEmailTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withBearer(null);
        $this->recaptchaAprova();
        Mail::fake();
    }

    /**
     * Cadastra pela API e devolve o token do link do e-mail de confirmação.
     */
    private function cadastrar(string $email = 'nova@teste.local'): string
    {
        $this->postJson('/auth/register', [
            'recaptcha_token' => 'token-de-teste',
            'nome' => 'Nova Pessoa',
            'email' => $email,
            'senha' => 'senhaForte1',
        ]);
        $this->assertResponseCode(201);

        $token = null;
        Mail::assertSent(VerificarEmail::class, function (VerificarEmail $mail) use (&$token, $email) {
            $token = $mail->token;

            return $mail->hasTo($email);
        });

        return (string) $token;
    }

    private function login(string $email, string $senha = 'senhaForte1'): void
    {
        $this->postJson('/auth/login', ['email' => $email, 'password' => $senha, 'recaptcha_token' => 'token-de-teste']);
    }

    public function test_cadastro_envia_o_link_e_nao_as_boas_vindas(): void
    {
        $this->cadastrar();

        Mail::assertNotSent(BoasVindas::class);
        $user = User::where('email', 'nova@teste.local')->firstOrFail();
        $this->assertNull($user->getAttribute('email_verificado_em'));
        // No banco fica só o hash do token
        $this->assertSame(64, strlen((string) $user->getAttribute('verificacao_token')));
    }

    public function test_login_antes_de_confirmar_e_recusado_com_codigo(): void
    {
        $this->cadastrar();

        $this->login('nova@teste.local');

        $this->assertResponseCode(403);
        $this->assertSame('email_nao_verificado', $this->responseJson()['errors']['codigo']);
    }

    public function test_senha_errada_nao_revela_que_falta_confirmar(): void
    {
        $this->cadastrar();

        $this->login('nova@teste.local', 'senhaErrada');

        $this->assertResponseCode(401);
    }

    public function test_link_confirma_envia_boas_vindas_e_libera_o_login(): void
    {
        $token = $this->cadastrar();

        $this->postJson('/auth/verify-email', ['token' => $token]);
        $this->assertResponseOk();
        Mail::assertSent(BoasVindas::class);

        $this->login('nova@teste.local');
        $this->assertResponseOk();

        // O link é de uso único
        $this->postJson('/auth/verify-email', ['token' => $token]);
        $this->assertResponseCode(400);
    }

    public function test_link_expirado_ou_invalido_e_recusado(): void
    {
        $token = $this->cadastrar();
        User::where('email', 'nova@teste.local')->update(['verificacao_token_expira' => now()->subMinute()]);

        $this->postJson('/auth/verify-email', ['token' => $token]);
        $this->assertResponseCode(400);

        $this->postJson('/auth/verify-email', ['token' => 'nao-existe']);
        $this->assertResponseCode(400);
    }

    public function test_reenvio_gera_link_novo_e_responde_igual_para_email_desconhecido(): void
    {
        $antigo = $this->cadastrar();

        $this->postJson('/auth/resend-verification', ['email' => 'NOVA@teste.local']);
        $this->assertResponseOk();
        $mensagem = $this->responseJson()['message'];
        Mail::assertSent(VerificarEmail::class, 2);

        // O link anterior deixa de valer
        $this->postJson('/auth/verify-email', ['token' => $antigo]);
        $this->assertResponseCode(400);

        $this->postJson('/auth/resend-verification', ['email' => 'ninguem@teste.local']);
        $this->assertResponseOk();
        $this->assertSame($mensagem, $this->responseJson()['message']);
        Mail::assertSent(VerificarEmail::class, 2);
    }

    public function test_cadastro_sem_recaptcha_e_recusado(): void
    {
        $this->postJson('/auth/register', ['nome' => 'Bot', 'email' => 'bot@teste.local', 'senha' => 'senhaForte1']);

        $this->assertResponseCode(400);
        $this->assertSame(0, User::where('email', 'bot@teste.local')->count());
    }

    public function test_login_social_numa_conta_nao_confirmada_descarta_a_senha_de_quem_criou(): void
    {
        // Alguém cadastrou o e-mail da vítima com uma senha própria e nunca confirmou
        $this->cadastrar('vitima@teste.local');
        $id = (int) User::where('email', 'vitima@teste.local')->value('id');
        $tokenDoInvasor = (new JwtService)->generateTokens(['id' => $id, 'email' => 'vitima@teste.local', 'nome' => 'x', 'role' => 'user']);
        usleep(2000);

        // A dona do e-mail entra pelo Google
        app(SocialAuthService::class)->findOrCreateUser(['email' => 'vitima@teste.local', 'name' => 'Vítima']);

        $user = User::findOrFail($id);
        $this->assertNotNull($user->getAttribute('email_verificado_em'));
        $this->assertNull($user->getAttribute('senha_hash'));
        $this->assertNull((new JwtService)->validateToken($tokenDoInvasor['refresh_token'], JwtService::TYPE_REFRESH));

        // A senha do invasor não entra mais
        $this->login('vitima@teste.local');
        $this->assertResponseCode(401);
    }

    public function test_conta_criada_pelo_login_social_ja_vem_confirmada(): void
    {
        $id = app(SocialAuthService::class)->findOrCreateUser(['email' => 'social@teste.local', 'name' => 'Social']);

        $this->assertNotNull(User::findOrFail($id)->getAttribute('email_verificado_em'));
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\StripeGatewayInterface;
use App\Services\JwtService;
use Tests\ApiTestCase;
use Tests\FakeStripeGateway;

/**
 * Revogação de tokens, confirmação da senha atual, e-mail sem diferença de maiúsculas e cobranças
 * que não podem sobrar no Stripe.
 */
class SegurancaContaTest extends ApiTestCase
{
    public function test_logout_invalida_o_refresh_token(): void
    {
        $user = $this->createUser();
        $refresh = $this->tokensFor($user)['refresh_token'];

        $this->withBearer(null);
        $this->cookie('refresh_token', $refresh);
        $this->post('/auth/logout');
        $this->assertResponseOk();

        // Uma cópia do cookie não serve mais
        $this->cookie('refresh_token', $refresh);
        $this->post('/auth/refresh');
        $this->assertResponseCode(401);
    }

    public function test_troca_de_senha_derruba_tokens_antigos_e_mantem_a_sessao_atual(): void
    {
        $user = $this->createUser();
        $antigos = $this->tokensFor($user);
        usleep(2000);

        $this->withBearer($antigos['access_token']);
        $this->putJson('/users/update/'.$user->id, ['senha' => 'novaSenha123', 'senha_atual' => 'secret123']);
        $this->assertResponseOk();
        $novoRefresh = $this->resposta()->getCookie('refresh_token', false)?->getValue();
        $this->assertNotEmpty($novoRefresh, 'A resposta deve trazer um refresh novo para a sessão atual');

        $jwt = new JwtService;
        $this->assertNull($jwt->validateToken($antigos['access_token'], JwtService::TYPE_ACCESS));
        $this->assertNull($jwt->validateToken($antigos['refresh_token'], JwtService::TYPE_REFRESH));
        $this->assertNotNull($jwt->validateToken((string) $novoRefresh, JwtService::TYPE_REFRESH));
    }

    public function test_trocar_email_exige_a_senha_atual(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->putJson('/users/update/'.$user->id, ['email' => 'novo@teste.local', 'senha_atual' => 'errada']);
        $this->assertResponseCode(400);

        $this->putJson('/users/update/'.$user->id, ['email' => 'Novo@Teste.local', 'senha_atual' => 'secret123']);
        $this->assertResponseOk();
        $this->assertSame('novo@teste.local', $this->fetch('Users', $user->id)->email);
    }

    public function test_admin_bloqueando_usuario_invalida_os_tokens_dele(): void
    {
        $admin = $this->createUser('admin');
        $alvo = $this->createUser();
        $tokensAlvo = $this->tokensFor($alvo);
        usleep(2000);

        $this->actingAs($admin, 'admin');
        $this->putJson('/users/update/'.$alvo->id, ['status' => 'bloqueado']);
        $this->assertResponseOk();

        $this->assertNull((new JwtService)->validateToken($tokensAlvo['access_token'], JwtService::TYPE_ACCESS));
    }

    public function test_email_cadastrado_com_outra_caixa_conta_como_existente(): void
    {
        $this->createUser('user', ['email' => 'maria@teste.local']);

        $this->withBearer(null);
        $this->recaptchaAprova();
        $this->postJson('/auth/register', ['recaptcha_token' => 'token-de-teste', 'nome' => 'Outra', 'email' => 'MARIA@Teste.Local', 'senha' => 'senha123']);

        $this->assertResponseCode(409);
    }

    public function test_email_invalido_no_cadastro_e_recusado(): void
    {
        $this->withBearer(null);
        $this->recaptchaAprova();
        $this->postJson('/auth/register', ['recaptcha_token' => 'token-de-teste', 'nome' => 'Fulano', 'email' => 'nao-e-email', 'senha' => 'senha123']);

        $this->assertResponseCode(400);
    }

    public function test_foto_de_perfil_precisa_ser_https(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->putJson('/users/update/'.$user->id, ['foto_perfil' => 'javascript:alert(1)']);

        $this->assertResponseCode(400);
    }

    public function test_plano_pago_sem_preco_no_ciclo_nao_ativa_de_graca(): void
    {
        $user = $this->createUser();
        $plano = $this->insert('Planos', ['nome' => 'Só mensal', 'preco_mensal' => 29.9, 'preco_anual' => 0, 'is_ativo' => true]);

        $this->actingAs($user);
        $this->postJson("/planos/{$plano->id}/assinar", ['ciclo' => 'anual']);

        $this->assertResponseCode(400);
        $this->assertSame(0, $this->tabela('Assinaturas')->where('usuario_id', $user->id)->where('plano_id', $plano->id)->count());
    }

    public function test_excluir_conta_cancela_a_assinatura_no_stripe(): void
    {
        $fake = new FakeStripeGateway;
        $this->mockService(StripeGatewayInterface::class, fn () => $fake);

        $user = $this->createUser();
        $plano = $this->insert('Planos', ['nome' => 'Pago', 'preco_mensal' => 19.9, 'preco_anual' => 199, 'is_ativo' => true]);
        $this->insert('Assinaturas', [
            'usuario_id' => $user->id,
            'plano_id' => $plano->id,
            'status' => 'active',
            'is_ativo' => true,
            'data_inicio' => date('Y-m-d H:i:s'),
            'data_fim' => date('Y-m-d H:i:s', strtotime('+20 days')),
            'stripe_subscription_id' => 'sub_que_cobra',
            'stripe_status' => 'active',
        ]);
        $this->insert('Tags', ['nome' => 'Verbs', 'criado_por' => $user->id]);

        $this->actingAs($user);
        $this->deleteJson('/users/delete/'.$user->id);

        $this->assertResponseOk();
        $this->assertSame([['subscriptionId' => 'sub_que_cobra']], $fake->chamadasDe('cancelarAgora'));
        $this->assertSame(0, $this->tabela('Tags')->where('nome', 'Verbs')->count());
    }
}

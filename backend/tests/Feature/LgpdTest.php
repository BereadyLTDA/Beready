<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

/**
 * LGPD: aceite dos termos no cadastro, exportação dos dados e exclusão confirmada por senha.
 */
class LgpdTest extends ApiTestCase
{
    public function test_cadastro_sem_aceite_dos_termos_e_recusado(): void
    {
        $this->withBearer(null);
        $this->recaptchaAprova();
        Mail::fake();

        $this->postJson('/auth/register', ['recaptcha_token' => 'x', 'nome' => 'Sem aceite', 'email' => 'sem@teste.local', 'senha' => 'senha123']);
        $this->assertResponseCode(422);
        $this->assertArrayHasKey('aceite_termos', $this->responseJson()['errors']);
        $this->assertSame(0, User::where('email', 'sem@teste.local')->count());

        $this->postJson('/auth/register', ['recaptcha_token' => 'x', 'aceite_termos' => true, 'nome' => 'Com aceite', 'email' => 'com@teste.local', 'senha' => 'senha123']);
        $this->assertResponseCode(201);
        $user = User::where('email', 'com@teste.local')->firstOrFail();
        $this->assertSame(config('beready.termos_versao'), $user->getAttribute('termos_versao'));
        $this->assertNotNull($user->getAttribute('termos_aceitos_em'));
    }

    public function test_conta_criada_pelo_login_social_registra_os_termos(): void
    {
        $id = app(SocialAuthService::class)->findOrCreateUser(['email' => 'social-termos@teste.local', 'name' => 'Social']);

        $this->assertSame(config('beready.termos_versao'), User::findOrFail($id)->getAttribute('termos_versao'));
    }

    public function test_exportar_traz_os_dados_do_aluno_sem_segredos_e_sem_dados_de_outros(): void
    {
        $user = $this->createUser('user', ['termos_versao' => '2026-11-20']);
        $this->insert('Flashcards', ['usuario_id' => $user->id, 'frente' => 'Hello', 'verso' => 'Olá']);
        $prompt = $this->insert('Prompts', ['usuario_id' => $user->id, 'texto_original' => 'Where is it?', 'idioma_original' => 'en-US']);
        $this->insert('Traducoes', ['prompt_id' => $prompt->id, 'texto_traduzido' => 'Onde fica?', 'idioma_destino' => 'pt-BR']);
        $outro = $this->createUser();
        $this->insert('Flashcards', ['usuario_id' => $outro->id, 'frente' => 'Segredo do outro', 'verso' => 'x']);

        $this->actingAs($user);
        $this->getJson('/users/me/exportar');

        $this->assertResponseOk();
        $this->assertStringContainsString('attachment', (string) $this->resposta()->headers->get('Content-Disposition'));
        $data = $this->responseJson()['data'];
        $this->assertSame($user->email, $data['perfil']['email']);
        $this->assertSame('2026-11-20', $data['termos']['versao_aceita']);
        $this->assertSame(['Hello'], array_column($data['flashcards'], 'frente'));
        $this->assertSame(['Onde fica?'], array_column($data['traducoes'], 'texto_traduzido'));

        $json = (string) json_encode($data);
        $this->assertStringNotContainsString('senha_hash', $json);
        $this->assertStringNotContainsString('reset_token', $json);
        $this->assertStringNotContainsString('Segredo do outro', $json);
    }

    public function test_excluir_a_propria_conta_exige_a_senha(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->deleteJson('/users/delete/'.$user->id, ['senha' => 'errada']);
        $this->assertResponseCode(400);
        $this->assertNotNull(User::find($user->id));

        $this->deleteJson('/users/delete/'.$user->id, ['senha' => 'secret123']);
        $this->assertResponseOk();
        $this->assertNull(User::find($user->id));
    }

    public function test_conta_so_com_login_social_exclui_sem_senha(): void
    {
        $id = app(SocialAuthService::class)->findOrCreateUser(['email' => 'so-social@teste.local', 'name' => 'Social']);
        $this->actingAs(User::findOrFail($id));

        $this->deleteJson('/users/delete/'.$id);

        $this->assertResponseOk();
    }
}

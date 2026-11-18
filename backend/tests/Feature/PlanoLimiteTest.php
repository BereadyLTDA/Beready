<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\ApiTestCase;

/**
 * PlanoLimiteMiddleware: limites de planos.limites nos POST de criação.
 * Limites dos seeds: Gratuito {flashcards 50, quizes 10, prompts 5}; Trial {999, 999, 20}; Premium {99999, 99999, 999}.
 */
class PlanoLimiteTest extends ApiTestCase
{
    private Model $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
        $this->actingAs($this->user);
    }

    public function test_bloqueia_prompt_no_limite_do_gratuito(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        $this->criarPrompts(5);

        $this->postJson('/prompts', ['texto_original' => 'Mais um']);

        $this->assertResponseCode(403);
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('limite de 5 prompts', $body['message']);
        $this->assertSame(['recurso' => 'prompts', 'limite' => 5, 'usados' => 5], $body['errors']['limite']);
        $this->assertSame(5, $this->contar('Prompts', 'usuario_id'));
    }

    public function test_bloqueia_quiz_e_flashcard_no_limite(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        for ($i = 0; $i < 10; $i++) {
            $this->insert('Quizes', ['usuario_id' => $this->user->id, 'titulo' => "Quiz {$i}"]);
        }
        for ($i = 0; $i < 50; $i++) {
            $this->insert('Flashcards', ['usuario_id' => $this->user->id, 'frente' => "F{$i}", 'verso' => 'v']);
        }

        $this->postJson('/quizes', ['titulo' => 'Quiz extra']);
        $this->assertResponseCode(403);
        $this->assertSame('quizes', $this->responseJson()['errors']['limite']['recurso']);

        $this->postJson('/flashcards', ['frente' => 'Extra', 'verso' => 'v']);
        $this->assertResponseCode(403);
        $this->assertSame(['recurso' => 'flashcards', 'limite' => 50, 'usados' => 50], $this->responseJson()['errors']['limite']);
    }

    public function test_gerar_quiz_tambem_respeita_o_limite(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        for ($i = 0; $i < 10; $i++) {
            $this->insert('Quizes', ['usuario_id' => $this->user->id, 'titulo' => "Quiz {$i}"]);
        }

        $this->postJson('/quizes/gerar', ['quantidade' => 5]);
        $this->assertResponseCode(403);
        $this->assertSame('quizes', $this->responseJson()['errors']['limite']['recurso']);
    }

    public function test_permite_abaixo_do_limite(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        $this->criarPrompts(4);

        $this->postJson('/prompts', ['texto_original' => 'Quinto']);

        $this->assertResponseCode(201);
        $this->assertSame(5, $this->contar('Prompts', 'usuario_id'));
    }

    public function test_registros_de_outros_usuarios_nao_contam(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        $outro = $this->createUser();
        for ($i = 0; $i < 5; $i++) {
            $this->insert('Prompts', ['usuario_id' => $outro->id, 'texto_original' => "Do outro {$i}"]);
        }

        $this->postJson('/prompts', ['texto_original' => 'Meu primeiro']);

        $this->assertResponseCode(201);
    }

    public function test_sem_assinatura_usa_os_limites_do_gratuito(): void
    {
        $this->criarPrompts(5);

        $this->postJson('/prompts', ['texto_original' => 'Sem plano']);

        $this->assertResponseCode(403);
        $this->assertSame(5, $this->responseJson()['errors']['limite']['limite']);
    }

    public function test_recurso_sem_chave_no_plano_eh_ilimitado(): void
    {
        // O Gratuito não tem a chave "tags"
        $this->assinar(self::PLANO_GRATUITO);
        for ($i = 0; $i < 60; $i++) {
            $this->insert('Tags', ['criado_por' => $this->user->id, 'nome' => "tag-{$i}"]);
        }

        $this->postJson('/tags', ['nome' => 'mais-uma']);

        $this->assertResponseCode(201);
    }

    public function test_limite_de_tags_por_criado_por(): void
    {
        $plano = $this->planoTemporario(['tags' => 2]);

        try {
            $this->assinar($plano->id);
            $this->insert('Tags', ['criado_por' => $this->user->id, 'nome' => 'a']);
            $this->insert('Tags', ['criado_por' => $this->user->id, 'nome' => 'b']);

            $this->postJson('/tags', ['nome' => 'c']);

            $this->assertResponseCode(403);
            $this->assertSame(['recurso' => 'tags', 'limite' => 2, 'usados' => 2], $this->responseJson()['errors']['limite']);
        } finally {
            $this->removerPlano($plano);
        }
    }

    public function test_limite_negativo_ou_muito_alto_eh_ilimitado(): void
    {
        $plano = $this->planoTemporario(['prompts' => -1, 'quizes' => 999999]);

        try {
            $this->assinar($plano->id);
            $this->criarPrompts(6);

            $this->postJson('/prompts', ['texto_original' => 'Ilimitado']);
            $this->assertResponseCode(201);

            $this->postJson('/quizes', ['titulo' => 'Ilimitado']);
            $this->assertResponseCode(201);
        } finally {
            $this->removerPlano($plano);
        }
    }

    public function test_admin_nao_tem_limite(): void
    {
        $admin = $this->createUser('admin');
        $this->actingAs($admin);
        $this->insert('Assinaturas', [
            'usuario_id' => $admin->id,
            'plano_id' => self::PLANO_GRATUITO,
            'status' => 'active',
            'is_ativo' => true,
        ]);
        for ($i = 0; $i < 5; $i++) {
            $this->insert('Prompts', ['usuario_id' => $admin->id, 'texto_original' => "Admin {$i}"]);
        }

        $this->postJson('/prompts', ['texto_original' => 'Sexto']);

        $this->assertResponseCode(201);
    }

    public function test_trial_tem_limite_proprio(): void
    {
        $this->assinar(self::PLANO_TRIAL, ['data_fim' => date('Y-m-d H:i:s', strtotime('+5 days'))]);
        $this->criarPrompts(5);

        // Acima do limite do Gratuito, abaixo do Trial (20)
        $this->postJson('/prompts', ['texto_original' => 'Sexto']);
        $this->assertResponseCode(201);

        $this->criarPrompts(14);
        $this->postJson('/prompts', ['texto_original' => 'Vigésimo primeiro']);
        $this->assertResponseCode(403);
        $this->assertSame(['recurso' => 'prompts', 'limite' => 20, 'usados' => 20], $this->responseJson()['errors']['limite']);
    }

    public function test_trial_expirado_volta_ao_limite_do_gratuito(): void
    {
        $this->assinar(self::PLANO_TRIAL, ['data_fim' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $this->criarPrompts(5);

        $this->postJson('/prompts', ['texto_original' => 'Sexto']);

        $this->assertResponseCode(403);
        $this->assertSame(5, $this->responseJson()['errors']['limite']['limite']);
    }

    public function test_premium_permite_acima_do_gratuito(): void
    {
        $this->assinar(self::PLANO_PREMIUM, ['data_fim' => date('Y-m-d H:i:s', strtotime('+1 month'))]);
        $this->criarPrompts(5);
        for ($i = 0; $i < 10; $i++) {
            $this->insert('Quizes', ['usuario_id' => $this->user->id, 'titulo' => "Quiz {$i}"]);
        }

        $this->postJson('/prompts', ['texto_original' => 'Sexto']);
        $this->assertResponseCode(201);

        $this->postJson('/quizes', ['titulo' => 'Décimo primeiro']);
        $this->assertResponseCode(201);
    }

    public function test_outros_metodos_e_rotas_nao_sao_afetados(): void
    {
        $this->assinar(self::PLANO_GRATUITO);
        $this->criarPrompts(5);

        $this->get('/prompts');
        $this->assertResponseOk();

        $prompt = $this->tabela('Prompts')->where(['usuario_id' => $this->user->id])->firstOrFail();
        $this->putJson('/prompts/'.$prompt->id, ['texto_original' => 'Editado']);
        $this->assertResponseOk();
    }

    private function assinar(int $planoId, array $data = []): Model
    {
        return $this->insert('Assinaturas', $data + [
            'usuario_id' => $this->user->id,
            'plano_id' => $planoId,
            'status' => 'active',
            'is_ativo' => true,
            'data_inicio' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
    }

    private function criarPrompts(int $quantidade): void
    {
        static $sequencia = 0;
        for ($i = 0; $i < $quantidade; $i++) {
            $this->insert('Prompts', ['usuario_id' => $this->user->id, 'texto_original' => 'Prompt '.(++$sequencia)]);
        }
    }

    private function contar(string $alias, string $coluna): int
    {
        return $this->tabela($alias)->where($coluna, $this->user->id)->count();
    }

    /**
     * planos não é limpa entre testes (vem dos seeds): remover no finally com removerPlano().
     */
    private function planoTemporario(array $limites): Model
    {
        return $this->insert('Planos', [
            'nome' => 'Plano Teste '.uniqid(),
            'preco_mensal' => 0,
            'preco_anual' => 0,
            'limites' => $limites,
            'is_ativo' => true,
        ]);
    }

    private function removerPlano(Model $plano): void
    {
        // Assinaturas apontam para o plano (FK): apaga antes
        $this->tabela('Assinaturas')->where('plano_id', $plano->id)->delete();
        $this->tabela('Planos')->delete($plano);
    }
}

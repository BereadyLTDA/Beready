<?php

namespace Tests\Feature;

use App\Models\Nivelamento;
use App\Models\NivelamentoQuestao;
use App\Models\User;
use App\Services\NivelamentoService;
use Tests\ApiTestCase;

/**
 * Teste de nivelamento adaptativo, corrigido no servidor.
 */
class NivelamentoTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser('user', ['nivel_ingles' => 'iniciante']);
        $this->actingAs($this->user);
    }

    /**
     * Responde a questão atual certo ou errado, consultando o gabarito no banco (como o servidor).
     *
     * @return array<string, mixed>
     */
    private function responder(int $id, bool $certo): array
    {
        $questaoId = (int) Nivelamento::findOrFail($id)->getAttribute('questao_atual_id');
        $questao = NivelamentoQuestao::findOrFail($questaoId);
        $correta = (int) $questao->getAttribute('correta');
        $alternativa = $certo ? $correta : ($correta + 1) % count($questao->getAttribute('alternativas'));

        $this->postJson("/nivelamento/{$id}/responder", ['alternativa' => $alternativa]);
        $this->assertResponseOk();

        return $this->responseJson()['data'];
    }

    public function test_regra_adaptativa_sobe_e_desce_com_duas_seguidas(): void
    {
        $s = new NivelamentoService;

        $this->assertSame(['B1', 1, 0], $s->proximoNivel('B1', true, 0, 0));
        $this->assertSame(['B2', 0, 0], $s->proximoNivel('B1', true, 1, 0));
        $this->assertSame(['B1', 0, 1], $s->proximoNivel('B1', false, 1, 0));
        $this->assertSame(['A2', 0, 0], $s->proximoNivel('B1', false, 0, 1));
        // Nos extremos não passa do limite
        $this->assertSame(['C1', 2, 0], $s->proximoNivel('C1', true, 1, 0));
        $this->assertSame(['A1', 0, 2], $s->proximoNivel('A1', false, 0, 1));
    }

    public function test_iniciar_devolve_a_questao_sem_o_gabarito(): void
    {
        $this->postJson('/nivelamento/iniciar');

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertFalse($data['concluido']);
        $this->assertSame(0, $data['respondidas']);
        $this->assertSame(12, $data['total']);
        $this->assertCount(4, $data['questao']['alternativas']);
        $this->assertArrayNotHasKey('correta', $data['questao']);
        $this->assertStringNotContainsString('"correta"', (string) json_encode($data['questao']));
        // Começa no B1
        $this->assertSame('B1', NivelamentoQuestao::findOrFail($data['questao']['id'])->getAttribute('nivel'));

        // Chamar de novo continua o mesmo teste
        $this->postJson('/nivelamento/iniciar');
        $this->assertSame($data['id'], $this->responseJson()['data']['id']);
    }

    public function test_acertando_tudo_termina_em_c1_e_atualiza_o_nivel_do_aluno(): void
    {
        $this->postJson('/nivelamento/iniciar');
        $id = $this->responseJson()['data']['id'];

        for ($i = 0; $i < 12; $i++) {
            $data = $this->responder($id, true);
            $this->assertTrue($data['correta']);
        }

        $this->assertTrue($data['concluido']);
        $this->assertNull($data['questao']);
        $this->assertSame('C1', $data['resultado']['nivel']);
        $this->assertSame(12, $data['resultado']['acertos']);
        $this->assertSame('avancado', $this->fetch('Users', $this->user->id)->nivel_ingles);

        // Não repete questões
        $ids = array_column((array) Nivelamento::findOrFail($id)->getAttribute('respostas'), 'questao_id');
        $this->assertCount(12, array_unique($ids));
    }

    public function test_errando_tudo_termina_em_a1(): void
    {
        $this->postJson('/nivelamento/iniciar');
        $id = $this->responseJson()['data']['id'];

        for ($i = 0; $i < 12; $i++) {
            $data = $this->responder($id, false);
        }

        $this->assertSame('A1', $data['resultado']['nivel']);
        $this->assertSame('iniciante', $this->fetch('Users', $this->user->id)->nivel_ingles);
    }

    public function test_nao_refaz_antes_de_30_dias_e_resultado_fica_disponivel(): void
    {
        $this->getJson('/nivelamento/resultado');
        $this->assertNull($this->responseJson()['data']);

        $this->postJson('/nivelamento/iniciar');
        $id = $this->responseJson()['data']['id'];
        for ($i = 0; $i < 12; $i++) {
            $this->responder($id, $i % 2 === 0);
        }

        $this->getJson('/nivelamento/resultado');
        $this->assertSame(6, $this->responseJson()['data']['acertos']);

        $this->postJson('/nivelamento/iniciar');
        $this->assertResponseCode(409);

        // Depois de 30 dias pode
        Nivelamento::whereKey($id)->update(['concluido_em' => now()->subDays(31)]);
        $this->postJson('/nivelamento/iniciar');
        $this->assertResponseOk();
        $this->assertNotSame($id, $this->responseJson()['data']['id']);
    }

    public function test_prazo_para_refazer_vem_da_configuracao(): void
    {
        // Na validação com usuários o teste final é refeito em 1 a 2 semanas
        config(['beready.nivelamento.dias_para_refazer' => 7]);

        $this->postJson('/nivelamento/iniciar');
        $id = $this->responseJson()['data']['id'];
        for ($i = 0; $i < 12; $i++) {
            $this->responder($id, true);
        }

        Nivelamento::whereKey($id)->update(['concluido_em' => now()->subDays(6)]);
        $this->postJson('/nivelamento/iniciar');
        $this->assertResponseCode(409);

        Nivelamento::whereKey($id)->update(['concluido_em' => now()->subDays(8)]);
        $this->getJson('/nivelamento/resultado');
        $this->assertSame(
            Nivelamento::findOrFail($id)->getAttribute('concluido_em')->addDays(7)->toAtomString(),
            $this->responseJson()['data']['pode_refazer_em']
        );
        $this->postJson('/nivelamento/iniciar');
        $this->assertResponseOk();
    }

    public function test_alternativa_invalida_e_teste_de_outro_aluno_sao_recusados(): void
    {
        $this->postJson('/nivelamento/iniciar');
        $id = $this->responseJson()['data']['id'];

        $this->postJson("/nivelamento/{$id}/responder", ['alternativa' => 9]);
        $this->assertResponseCode(422);

        $this->actingAs($this->createUser());
        $this->postJson("/nivelamento/{$id}/responder", ['alternativa' => 0]);
        $this->assertResponseCode(404);
    }
}

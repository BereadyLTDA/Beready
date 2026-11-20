<?php

namespace Tests\Feature;

use App\Mail\LembreteEstudo;
use App\Models\User;
use App\Services\LembreteService;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

/**
 * Meta diária, conquistas (regras no servidor) e lembrete por e-mail.
 */
class GamificacaoTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->actingAs($this->user);
    }

    private function flashcard(array $data = []): int
    {
        return (int) $this->insert('Flashcards', $data + [
            'usuario_id' => $this->user->id,
            'frente' => 'Hello',
            'verso' => 'Olá',
        ])->id;
    }

    private function revisoes(int $quantidade, ?string $quando = null): void
    {
        // Card já revisado: próxima revisão no futuro (um card novo nasce vencido e mudaria as contas)
        $id = $this->flashcard(['proxima_revisao' => date('Y-m-d H:i:s', strtotime('+10 days'))]);
        for ($i = 0; $i < $quantidade; $i++) {
            $this->insert('RespostasUsuario', [
                'usuario_id' => $this->user->id,
                'tipo' => 'flashcard',
                'referencia_id' => $id,
                'correto' => true,
                'criado_em' => $quando ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function conquistas(): array
    {
        $this->getJson('/conquistas');
        $this->assertResponseOk();

        return array_column($this->responseJson()['data'], null, 'codigo');
    }

    public function test_conquistas_saem_das_metricas_do_servidor(): void
    {
        $inicio = $this->conquistas();
        $this->assertFalse($inicio['primeiro_card']['conquistada']);
        $this->assertSame(0, $inicio['cards_50']['atual']);

        $this->revisoes(50);
        $this->insert('ProgressoUsuario', ['usuario_id' => $this->user->id, 'sequencia_atual' => 3, 'maior_sequencia' => 8]);
        $this->insert('PronunciaTentativas', ['usuario_id' => $this->user->id, 'frase' => 'a b', 'reconhecido' => 'a b', 'nota' => 95]);

        $lista = $this->conquistas();
        foreach (['primeiro_card', 'cards_50', 'sequencia_7', 'pronuncia_90'] as $codigo) {
            $this->assertTrue($lista[$codigo]['conquistada'], $codigo);
            $this->assertTrue($lista[$codigo]['nova'], $codigo);
        }
        foreach (['cards_200', 'sequencia_30', 'quiz_gabaritado', 'frases_ia_10'] as $codigo) {
            $this->assertFalse($lista[$codigo]['conquistada'], $codigo);
        }
        $this->assertSame(50, $lista['cards_200']['atual']);
        $this->assertSame(200, $lista['cards_200']['alvo']);

        // Depois de vistas, deixam de ser novas, mas continuam conquistadas
        $this->postJson('/conquistas/vistas');
        $this->assertResponseOk();
        $depois = $this->conquistas();
        $this->assertTrue($depois['cards_50']['conquistada']);
        $this->assertFalse($depois['cards_50']['nova']);
    }

    public function test_conquistas_sao_por_aluno(): void
    {
        $outro = $this->createUser();
        $this->insert('Flashcards', ['usuario_id' => $outro->id, 'frente' => 'x', 'verso' => 'y']);

        $this->assertFalse($this->conquistas()['primeiro_card']['conquistada']);
    }

    public function test_meta_diaria_conta_so_as_revisoes_de_hoje(): void
    {
        $this->revisoes(3);
        $this->revisoes(5, date('Y-m-d H:i:s', strtotime('-2 days')));

        $this->getJson('/meta-diaria');

        $this->assertResponseOk();
        $this->assertSame(['meta' => 10, 'feitos_hoje' => 3, 'sequencia_atual' => 0], $this->responseJson()['data']);
    }

    public function test_meta_diaria_vem_das_preferencias_e_so_aceita_valores_da_lista(): void
    {
        $this->postJson('/preferencias', ['meta_diaria_cards' => 20]);
        $this->assertResponseOk();
        $this->getJson('/meta-diaria');
        $this->assertSame(20, $this->responseJson()['data']['meta']);

        $this->postJson('/preferencias', ['meta_diaria_cards' => 999]);
        $this->assertResponseCode(422);
    }

    public function test_lembrete_vai_so_para_quem_tem_cards_vencidos_e_parou(): void
    {
        Mail::fake();
        $ontem = date('Y-m-d H:i:s', strtotime('-1 day'));

        // Parou há 5 dias e tem card vencido: recebe
        $this->flashcard(['proxima_revisao' => $ontem]);
        $this->revisoes(1, date('Y-m-d H:i:s', strtotime('-5 days')));

        // Estudou hoje: não recebe
        $ativo = $this->createUser();
        $this->insert('Flashcards', ['usuario_id' => $ativo->id, 'frente' => 'a', 'verso' => 'b', 'proxima_revisao' => $ontem]);
        $this->insert('RespostasUsuario', ['usuario_id' => $ativo->id, 'tipo' => 'flashcard', 'referencia_id' => 1, 'correto' => true]);

        // Desligou as notificações: não recebe
        $silencioso = $this->createUser();
        $this->insert('Flashcards', ['usuario_id' => $silencioso->id, 'frente' => 'a', 'verso' => 'b', 'proxima_revisao' => $ontem]);
        $this->insert('PreferenciasUsuario', ['usuario_id' => $silencioso->id, 'notificacoes_ativas' => false]);

        // Sem card vencido: não recebe
        $emDia = $this->createUser();
        $this->insert('Flashcards', ['usuario_id' => $emDia->id, 'frente' => 'a', 'verso' => 'b', 'proxima_revisao' => date('Y-m-d H:i:s', strtotime('+3 days'))]);

        $this->assertSame(1, app(LembreteService::class)->enviar());
        Mail::assertSent(LembreteEstudo::class, fn (LembreteEstudo $m) => $m->hasTo($this->user->email) && $m->cardsParaRevisar === 1);

        // Não repete no dia seguinte (intervalo mínimo entre lembretes)
        $this->assertSame(0, app(LembreteService::class)->enviar());
    }
}

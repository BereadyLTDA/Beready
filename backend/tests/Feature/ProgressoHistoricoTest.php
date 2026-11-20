<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\ApiTestCase;

/**
 * Séries dos gráficos de progresso.
 */
class ProgressoHistoricoTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->actingAs($this->user);
    }

    private function registrarResposta(string $tipo, bool $correto, Carbon $quando, ?int $usuarioId = null): void
    {
        $this->insert('RespostasUsuario', [
            'usuario_id' => $usuarioId ?? $this->user->id,
            'tipo' => $tipo,
            'referencia_id' => 1,
            'correto' => $correto,
            'criado_em' => $quando->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_series_agrupadas_por_dia_e_semana(): void
    {
        $hoje = Carbon::today()->setTime(10, 0);
        $this->registrarResposta('flashcard', true, $hoje);
        $this->registrarResposta('flashcard', false, $hoje);
        $this->registrarResposta('quiz', true, $hoje);
        $this->registrarResposta('flashcard', true, $hoje->copy()->subDays(3));
        // Fora da janela de 30 dias: não entra em revisoes_por_dia
        $this->registrarResposta('flashcard', true, $hoje->copy()->subDays(40));
        // De outro aluno: não entra
        $outro = $this->createUser();
        $this->registrarResposta('flashcard', true, $hoje, $outro->id);

        $this->insert('PronunciaTentativas', ['usuario_id' => $this->user->id, 'frase' => 'a', 'reconhecido' => 'a', 'nota' => 80, 'criado_em' => $hoje->format('Y-m-d H:i:s')]);
        $this->insert('PronunciaTentativas', ['usuario_id' => $this->user->id, 'frase' => 'a', 'reconhecido' => 'a', 'nota' => 100, 'criado_em' => $hoje->format('Y-m-d H:i:s')]);

        $this->getJson('/progresso/historico');

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];

        $dias = array_column($data['revisoes_por_dia'], 'total', 'dia');
        $this->assertCount(30, $dias);
        $this->assertSame(2, $dias[$hoje->toDateString()]);
        $this->assertSame(1, $dias[$hoje->copy()->subDays(3)->toDateString()]);
        $this->assertSame(3, array_sum($dias));

        $semanas = array_column($data['acertos_por_semana'], null, 'semana');
        $this->assertCount(12, $semanas);
        $atual = $semanas[$hoje->copy()->startOfWeek()->toDateString()];
        $this->assertGreaterThanOrEqual(3, $atual['total']);
        $this->assertSame((int) round($atual['corretas'] / $atual['total'] * 100), $atual['taxa']);

        $pronuncia = array_column($data['pronuncia_por_semana'], null, 'semana')[$hoje->copy()->startOfWeek()->toDateString()];
        $this->assertSame(['semana' => $hoje->copy()->startOfWeek()->toDateString(), 'tentativas' => 2, 'media' => 90], $pronuncia);
    }

    public function test_distribuicao_dos_cards_pela_repeticao_espacada(): void
    {
        $card = fn (array $d) => $this->insert('Flashcards', $d + ['usuario_id' => $this->user->id, 'frente' => 'a', 'verso' => 'b']);
        $card(['repeticoes' => 0]);
        $card(['repeticoes' => 0]);
        $card(['repeticoes' => 2, 'intervalo_dias' => 6]);
        $card(['repeticoes' => 5, 'intervalo_dias' => 40]);

        $this->getJson('/progresso/historico');

        $this->assertSame(['novos' => 2, 'aprendendo' => 1, 'dominados' => 1], $this->responseJson()['data']['distribuicao_cards']);
    }
}

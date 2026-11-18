<?php

namespace Tests\Unit;

use App\Services\QuizTentativaService;
use App\Services\RepeticaoEspacadaService;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Cálculo do SM-2 simplificado (sem banco) e normalização da resposta "completar".
 */
class RepeticaoEspacadaServiceTest extends TestCase
{
    private RepeticaoEspacadaService $service;

    private DateTimeImmutable $agora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RepeticaoEspacadaService;
        $this->agora = new DateTimeImmutable('2026-10-07 15:30:00');
    }

    private function cardNovo(): array
    {
        return ['repeticoes' => 0, 'intervalo_dias' => 0, 'fator_ease' => 2.5];
    }

    public function test_primeiro_acerto_bom_revisa_amanha_no_inicio_do_dia(): void
    {
        $r = $this->service->calcular($this->cardNovo(), 'bom', $this->agora);

        $this->assertSame(1, $r['repeticoes']);
        $this->assertSame(1, $r['intervalo_dias']);
        $this->assertSame(2.5, $r['fator_ease']);
        $this->assertSame('2026-10-08 00:00:00', $r['proxima_revisao']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 15:30:00', $r['ultima_revisao']->format('Y-m-d H:i:s'));
    }

    public function test_facil_aumenta_a_facilidade(): void
    {
        $r = $this->service->calcular($this->cardNovo(), 'facil', $this->agora);

        $this->assertSame(1, $r['repeticoes']);
        $this->assertSame(1, $r['intervalo_dias']);
        $this->assertSame(2.6, $r['fator_ease']);
    }

    public function test_segundo_acerto_revisa_em_seis_dias(): void
    {
        $r = $this->service->calcular(['repeticoes' => 1, 'intervalo_dias' => 1, 'fator_ease' => 2.5], 'bom', $this->agora);

        $this->assertSame(2, $r['repeticoes']);
        $this->assertSame(6, $r['intervalo_dias']);
        $this->assertSame('2026-10-13 00:00:00', $r['proxima_revisao']->format('Y-m-d H:i:s'));
    }

    public function test_a_partir_do_terceiro_acerto_o_intervalo_cresce_com_a_facilidade(): void
    {
        $bom = $this->service->calcular(['repeticoes' => 2, 'intervalo_dias' => 6, 'fator_ease' => 2.5], 'bom', $this->agora);
        $this->assertSame(3, $bom['repeticoes']);
        $this->assertSame(15, $bom['intervalo_dias']); // 6 × 2.5

        $facil = $this->service->calcular(['repeticoes' => 2, 'intervalo_dias' => 6, 'fator_ease' => 2.5], 'facil', $this->agora);
        $this->assertSame(16, $facil['intervalo_dias']); // 6 × 2.6 = 15.6
        $this->assertGreaterThan($bom['intervalo_dias'], $facil['intervalo_dias']);
    }

    public function test_errei_reinicia_e_revisa_no_mesmo_dia(): void
    {
        $r = $this->service->calcular(['repeticoes' => 5, 'intervalo_dias' => 40, 'fator_ease' => 2.5], 'errei', $this->agora);

        $this->assertSame(0, $r['repeticoes']);
        $this->assertSame(0, $r['intervalo_dias']);
        $this->assertSame(1.96, $r['fator_ease']); // 2.5 − 0.54
        $this->assertSame('2026-10-07 15:40:00', $r['proxima_revisao']->format('Y-m-d H:i:s'));
    }

    public function test_facilidade_nunca_fica_abaixo_do_minimo(): void
    {
        $r = $this->service->calcular(['repeticoes' => 0, 'intervalo_dias' => 0, 'fator_ease' => 1.4], 'errei', $this->agora);
        $this->assertSame(RepeticaoEspacadaService::FATOR_EASE_MINIMO, $r['fator_ease']);

        $r = $this->service->calcular(['repeticoes' => 3, 'intervalo_dias' => 10, 'fator_ease' => 1.3], 'bom', $this->agora);
        $this->assertSame(1.3, $r['fator_ease']);
        $this->assertSame(13, $r['intervalo_dias']);
    }

    public function test_estado_vazio_ou_fator_como_string_usa_padroes(): void
    {
        $r = $this->service->calcular([], 'bom', $this->agora);
        $this->assertSame(1, $r['repeticoes']);
        $this->assertSame(2.5, $r['fator_ease']);

        // numeric do Postgres chega como string
        $r = $this->service->calcular(['repeticoes' => 2, 'intervalo_dias' => 6, 'fator_ease' => '2.50'], 'bom', $this->agora);
        $this->assertSame(15, $r['intervalo_dias']);
    }

    public function test_nota_invalida_lanca_excecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calcular($this->cardNovo(), 'dificil', $this->agora);
    }

    public function test_normalizacao_da_resposta_completar(): void
    {
        $this->assertSame('sao paulo', QuizTentativaService::normalizarTexto("  São   PAULO \n"));
        $this->assertSame(
            QuizTentativaService::normalizarTexto('Ação'),
            QuizTentativaService::normalizarTexto('acao')
        );
        $this->assertNotSame(
            QuizTentativaService::normalizarTexto('casa'),
            QuizTentativaService::normalizarTexto('caça')
        );
    }
}

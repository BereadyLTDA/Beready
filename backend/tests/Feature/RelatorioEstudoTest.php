<?php

namespace Tests\Feature;

use App\Models\ConquistaUsuario;
use App\Models\Nivelamento;
use App\Models\User;
use Tests\ApiTestCase;

/**
 * Exportação anonimizada das métricas da validação com usuários (php artisan beready:relatorio-estudo).
 */
class RelatorioEstudoTest extends ApiTestCase
{
    private string $saida;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida = tempnam(sys_get_temp_dir(), 'relatorio').'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->saida);
        parent::tearDown();
    }

    private function respostaDe(User $user, string $tipo, bool $correto, string $quando): void
    {
        $this->insert('RespostasUsuario', [
            'usuario_id' => $user->id,
            'tipo' => $tipo,
            'referencia_id' => 1,
            'correto' => $correto,
            'criado_em' => $quando,
        ]);
    }

    private function nivelamento(User $user, string $nivel, string $quando): void
    {
        $n = Nivelamento::create(['usuario_id' => $user->id, 'nivel_atual' => 'B1']);
        $n->forceFill(['respostas' => [], 'nivel_resultado' => $nivel, 'concluido_em' => $quando])->save();
    }

    /**
     * @return list<array<string, string>>
     */
    private function lerCsv(string $separador = ','): array
    {
        $linhas = array_map(fn ($l) => str_getcsv($l, $separador, '"', ''), file($this->saida, FILE_IGNORE_NEW_LINES));
        $cabecalho = array_shift($linhas);

        return array_map(fn ($l) => array_combine($cabecalho, $l), $linhas);
    }

    public function test_exporta_metricas_do_periodo_sem_dados_pessoais(): void
    {
        $ana = $this->createUser('user', ['nome' => 'Ana Secreta', 'nivel_ingles' => 'intermediario']);
        $bruno = $this->createUser();

        // Antes do período: não conta
        $this->respostaDe($ana, 'flashcard', false, '2026-10-20 10:00:00');
        // Primeira metade do período (01 a 14/11): 1 de 2 certas
        $this->respostaDe($ana, 'flashcard', true, '2026-11-02 10:00:00');
        $this->respostaDe($ana, 'flashcard', false, '2026-11-02 11:00:00');
        // Segunda metade: 3 de 3 certas (uma de quiz)
        $this->respostaDe($ana, 'flashcard', true, '2026-11-12 10:00:00');
        $this->respostaDe($ana, 'flashcard', true, '2026-11-13 10:00:00');
        $this->respostaDe($ana, 'quiz', true, '2026-11-13 10:05:00');

        $this->insert('Flashcards', ['usuario_id' => $ana->id, 'frente' => 'Hi', 'verso' => 'Oi', 'criado_em' => '2026-11-05 09:00:00']);
        $prompt = $this->insert('Prompts', ['usuario_id' => $ana->id, 'texto_original' => 'texto privado', 'criado_em' => '2026-11-05 09:30:00']);
        $this->insert('FrasesSemelhantes', ['prompt_id' => $prompt->id, 'frase_semelhante' => 'a']);
        $this->insert('FrasesSemelhantes', ['prompt_id' => $prompt->id, 'frase_semelhante' => 'b']);
        $this->insert('PronunciaTentativas', ['usuario_id' => $ana->id, 'frase' => 'x', 'reconhecido' => 'x', 'nota' => 80, 'criado_em' => '2026-11-06 08:00:00']);
        $this->insert('PronunciaTentativas', ['usuario_id' => $ana->id, 'frase' => 'x', 'reconhecido' => 'x', 'nota' => 95, 'criado_em' => '2026-11-06 08:05:00']);
        $this->insert('ProgressoUsuario', ['usuario_id' => $ana->id, 'sequencia_atual' => 2, 'maior_sequencia' => 5]);
        ConquistaUsuario::create(['usuario_id' => $ana->id, 'codigo' => 'primeiro_card']);
        ConquistaUsuario::where('usuario_id', $ana->id)->update(['conquistada_em' => '2026-11-05 09:00:00']);

        $this->nivelamento($ana, 'A2', '2026-10-31 20:00:00');
        $this->nivelamento($ana, 'B1', '2026-11-14 20:00:00');

        $this->artisan('beready:relatorio-estudo', [
            // Fora de ordem de cadastro e com maiúsculas: o código segue a ordem da lista
            '--emails' => strtoupper($bruno->email).', '.$ana->email.', ninguem@teste.local',
            '--de' => '2026-11-01',
            '--ate' => '2026-11-14',
            '--saida' => $this->saida,
        ])
            ->expectsOutputToContain('Linha 3 da lista')
            ->expectsOutputToContain('2 participante(s) exportado(s)')
            ->assertExitCode(0);

        $conteudo = (string) file_get_contents($this->saida);
        $this->assertStringNotContainsString('Ana Secreta', $conteudo);
        $this->assertStringNotContainsString($ana->email, $conteudo);
        $this->assertStringNotContainsString('texto privado', $conteudo);

        [$p1, $p2] = $this->lerCsv();

        $this->assertSame('P01', $p1['participante']);
        $this->assertSame('0', $p1['dias_ativos']);
        $this->assertSame('', $p1['acerto_flashcard_pct']);
        $this->assertSame('', $p1['nivelamento_inicial']);

        $this->assertSame([
            'participante' => 'P02',
            'nivel_ingles_atual' => 'intermediario',
            'nivelamento_inicial' => 'A2',
            'nivelamento_final' => 'B1',
            'dias_ativos' => '5', // 02, 05, 06, 12 e 13/11
            'flashcards_criados' => '1',
            'revisoes_flashcard' => '4',
            'acerto_flashcard_pct' => '75',
            'respostas_quiz' => '1',
            'acerto_quiz_pct' => '100',
            'acerto_primeira_metade_pct' => '50',
            'acerto_segunda_metade_pct' => '100',
            'prompts_criados' => '1',
            'frases_semelhantes' => '2',
            'pronuncia_tentativas' => '2',
            'pronuncia_nota_media' => '87.5',
            'maior_sequencia' => '5',
            'conquistas_no_periodo' => '1',
        ], $p2);
    }

    public function test_um_unico_nivelamento_fica_so_como_inicial_e_separador_configuravel(): void
    {
        $user = $this->createUser();
        $this->nivelamento($user, 'B2', '2026-11-03 10:00:00');
        $arquivo = tempnam(sys_get_temp_dir(), 'emails');
        file_put_contents($arquivo, "# participantes\n{$user->email}\n\n");

        $this->artisan('beready:relatorio-estudo', [
            '--arquivo' => $arquivo,
            '--de' => '2026-11-01',
            '--ate' => '2026-11-14',
            '--saida' => $this->saida,
            '--separador' => ';',
        ])->assertExitCode(0);
        @unlink($arquivo);

        [$p1] = $this->lerCsv(';');
        $this->assertSame('B2', $p1['nivelamento_inicial']);
        $this->assertSame('', $p1['nivelamento_final']);
    }

    public function test_exige_participantes_e_periodo_valido(): void
    {
        $this->artisan('beready:relatorio-estudo', ['--de' => '2026-11-01'])
            ->expectsOutputToContain('Informe os participantes')
            ->assertExitCode(1);

        $this->artisan('beready:relatorio-estudo', ['--emails' => 'a@b.c', '--de' => '01/11/2026'])
            ->expectsOutputToContain('AAAA-MM-DD')
            ->assertExitCode(1);

        $this->artisan('beready:relatorio-estudo', ['--emails' => 'a@b.c', '--de' => '2026-11-10', '--ate' => '2026-11-01'])
            ->assertExitCode(1);
    }
}

<?php

namespace Tests\Feature;

use App\Services\QuizQuestaoService;
use Illuminate\Database\Eloquent\Model;
use Tests\ApiTestCase;

/**
 * Questões reais dos quizzes: CRUD (dono), validação, correção no servidor, tentativa,
 * geração a partir dos flashcards e IDOR nas rotas novas.
 */
class QuizQuestoesTest extends ApiTestCase
{
    protected array $fixtures = [
        'app.Users',
        'app.Assinaturas',
        'app.Flashcards',
        'app.FlashcardTags',
        'app.Quizes',
        'app.QuizQuestoes',
        'app.QuizAlternativas',
        'app.Prompts',
        'app.Tags',
        'app.ProgressoUsuario',
        'app.RespostasUsuario',
        'app.UsuarioRoles',
        'app.LogsPermissoes',
    ];

    private Model $userA;

    private Model $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = $this->createUser();
        $this->userB = $this->createUser();
        $this->actingAs($this->userA);
    }

    // ----------------------------------------------------------------- helpers

    private function quiz(Model $dono, array $data = []): Model
    {
        return $this->insert('Quizes', $data + ['usuario_id' => $dono->id, 'titulo' => 'Quiz', 'publico' => false]);
    }

    private function multipla(string $enunciado = 'Capital da França?'): array
    {
        return [
            'tipo' => 'multipla_escolha',
            'enunciado' => $enunciado,
            'explicacao' => 'Paris é a capital.',
            'alternativas' => [
                ['texto' => 'Lyon', 'correta' => false],
                ['texto' => 'Paris', 'correta' => true],
                ['texto' => 'Nice', 'correta' => false],
            ],
        ];
    }

    private function completar(string $esperada = 'São Paulo'): array
    {
        return [
            'tipo' => 'completar',
            'enunciado' => 'Maior cidade do Brasil: ____',
            'resposta_esperada' => $esperada,
        ];
    }

    /**
     * Cria a questão direto pelo serviço (para quizzes de outros usuários), com gabarito.
     */
    private function questao(Model $quiz, array $data): array
    {
        return (new QuizQuestaoService)->criar((int) $quiz->id, $data);
    }

    private function idCorreta(array $questao): int
    {
        foreach ($questao['alternativas'] as $alternativa) {
            if ($alternativa['correta']) {
                return $alternativa['id'];
            }
        }
        $this->fail('Questão sem alternativa correta');
    }

    private function idErrada(array $questao): int
    {
        foreach ($questao['alternativas'] as $alternativa) {
            if (! $alternativa['correta']) {
                return $alternativa['id'];
            }
        }
        $this->fail('Questão sem alternativa errada');
    }

    // -------------------------------------------------------------------- CRUD

    public function test_cria_questao_de_multipla_escolha_e_atualiza_total(): void
    {
        $quiz = $this->quiz($this->userA);

        $this->postJson("/quizes/{$quiz->id}/questoes", $this->multipla());

        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame('multipla_escolha', $data['tipo']);
        $this->assertSame(1, $data['ordem']);
        $this->assertCount(3, $data['alternativas']);
        $this->assertSame([false, true, false], array_column($data['alternativas'], 'correta'));
        $this->assertSame(1, $this->fetch('Quizes', $quiz->id)->total_questoes);

        $this->postJson("/quizes/{$quiz->id}/questoes", $this->completar());
        $this->assertResponseCode(201);
        $this->assertSame(2, $this->responseJson()['data']['ordem']);
        $this->assertSame(2, $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    public function test_get_do_quiz_traz_questoes_sem_gabarito(): void
    {
        $quiz = $this->quiz($this->userA);
        $this->questao($quiz, $this->multipla());
        $this->questao($quiz, $this->completar());

        $this->get("/quizes/{$quiz->id}");

        $this->assertResponseOk();
        $questoes = $this->responseJson()['data']['questoes'];
        $this->assertCount(2, $questoes);
        foreach ($questoes as $questao) {
            $this->assertArrayNotHasKey('resposta_esperada', $questao);
            $this->assertArrayNotHasKey('explicacao', $questao);
            foreach ($questao['alternativas'] as $alternativa) {
                $this->assertArrayNotHasKey('correta', $alternativa);
            }
        }
        $this->assertStringNotContainsString('São Paulo', (string) $this->ultimaResposta->getContent());

        // O editor (dono) recebe o gabarito
        $this->get("/quizes/{$quiz->id}/questoes");
        $this->assertResponseOk();
        $editor = $this->responseJson()['data'];
        $this->assertSame('São Paulo', $editor[1]['resposta_esperada']);
        $this->assertArrayHasKey('correta', $editor[0]['alternativas'][0]);
    }

    public function test_quiz_antigo_sem_questoes_nao_quebra(): void
    {
        $quiz = $this->quiz($this->userA, ['descricao' => 'Quiz antigo']);

        $this->get("/quizes/{$quiz->id}");

        $this->assertResponseOk();
        $this->assertSame([], $this->responseJson()['data']['questoes']);
    }

    public function test_validacao_da_multipla_escolha(): void
    {
        $quiz = $this->quiz($this->userA);

        $umaAlternativa = $this->multipla();
        $umaAlternativa['alternativas'] = [['texto' => 'Paris', 'correta' => true]];
        $this->postJson("/quizes/{$quiz->id}/questoes", $umaAlternativa);
        $this->assertResponseCode(422);
        $this->assertArrayHasKey('minimo', $this->responseJson()['errors']['alternativas']);

        $duasCorretas = $this->multipla();
        $duasCorretas['alternativas'][0]['correta'] = true;
        $this->postJson("/quizes/{$quiz->id}/questoes", $duasCorretas);
        $this->assertResponseCode(422);
        $this->assertArrayHasKey('umaCorreta', $this->responseJson()['errors']['alternativas']);

        $nenhumaCorreta = $this->multipla();
        $nenhumaCorreta['alternativas'][1]['correta'] = false;
        $this->postJson("/quizes/{$quiz->id}/questoes", $nenhumaCorreta);
        $this->assertResponseCode(422);
        $this->assertArrayHasKey('umaCorreta', $this->responseJson()['errors']['alternativas']);

        $semTexto = $this->multipla('');
        $semTexto['alternativas'][2]['texto'] = '  ';
        $this->postJson("/quizes/{$quiz->id}/questoes", $semTexto);
        $this->assertResponseCode(422);
        $errors = $this->responseJson()['errors'];
        $this->assertArrayHasKey('enunciado', $errors);
        $this->assertArrayHasKey('alternativas.2.texto', $errors);

        $this->postJson("/quizes/{$quiz->id}/questoes", ['tipo' => 'verdadeiro_falso', 'enunciado' => 'X']);
        $this->assertResponseCode(422);
        $this->assertArrayHasKey('tipo', $this->responseJson()['errors']);

        $this->assertSame(0, $this->tabela('QuizQuestoes')->count());
        $this->assertSame(0, (int) $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    public function test_completar_exige_resposta_esperada(): void
    {
        $quiz = $this->quiz($this->userA);

        $this->postJson("/quizes/{$quiz->id}/questoes", $this->completar('   '));

        $this->assertResponseCode(422);
        $this->assertArrayHasKey('resposta_esperada', $this->responseJson()['errors']);
    }

    public function test_edita_e_exclui_questao(): void
    {
        $quiz = $this->quiz($this->userA);
        $questao = $this->questao($quiz, $this->multipla());

        // Só o enunciado: alternativas mantidas
        $this->putJson("/quizes/{$quiz->id}/questoes/{$questao['id']}", ['enunciado' => 'Capital da França (editada)?']);
        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertSame('Capital da França (editada)?', $data['enunciado']);
        $this->assertSame(['Lyon', 'Paris', 'Nice'], array_column($data['alternativas'], 'texto'));

        // Troca o tipo para completar
        $this->putJson("/quizes/{$quiz->id}/questoes/{$questao['id']}", ['tipo' => 'completar', 'resposta_esperada' => 'Paris']);
        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertSame('completar', $data['tipo']);
        $this->assertSame([], $data['alternativas']);
        $this->assertSame(0, $this->tabela('QuizAlternativas')->count());

        // Edição inválida não altera nada
        $this->putJson("/quizes/{$quiz->id}/questoes/{$questao['id']}", ['resposta_esperada' => '']);
        $this->assertResponseCode(422);
        $this->assertSame('Paris', $this->fetch('QuizQuestoes', $questao['id'])->resposta_esperada);

        $this->delete("/quizes/{$quiz->id}/questoes/{$questao['id']}");
        $this->assertResponseOk();
        $this->assertNull($this->fetch('QuizQuestoes', $questao['id']));
        $this->assertSame(0, $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    public function test_substitui_todas_as_questoes_na_ordem_da_lista(): void
    {
        $quiz = $this->quiz($this->userA);
        $antiga = $this->questao($quiz, $this->multipla('Antiga'));

        $this->putJson("/quizes/{$quiz->id}/questoes", [
            'questoes' => [$this->completar(), $this->multipla('Segunda'), $this->multipla('Terceira')],
        ]);

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertSame(['completar', 'multipla_escolha', 'multipla_escolha'], array_column($data, 'tipo'));
        $this->assertSame([1, 2, 3], array_column($data, 'ordem'));
        $this->assertSame('Segunda', $data[1]['enunciado']);
        $this->assertNull($this->fetch('QuizQuestoes', $antiga['id']));
        $this->assertSame(3, $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    public function test_substituicao_invalida_nao_altera_nada(): void
    {
        $quiz = $this->quiz($this->userA);
        $antiga = $this->questao($quiz, $this->multipla('Antiga'));
        $invalida = $this->multipla('Inválida');
        $invalida['alternativas'] = [];

        $this->putJson("/quizes/{$quiz->id}/questoes", ['questoes' => [$this->completar(), $invalida]]);

        $this->assertResponseCode(422);
        $this->assertArrayHasKey('questoes.1.alternativas', $this->responseJson()['errors']);
        $this->assertNotNull($this->fetch('QuizQuestoes', $antiga['id']));
        $this->assertSame(1, $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    public function test_cria_quiz_ja_com_questoes(): void
    {
        $this->postJson('/quizes', [
            'titulo' => 'Quiz completo',
            'total_questoes' => 50,
            'questoes' => [$this->multipla(), $this->completar()],
        ]);

        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame(2, $data['total_questoes']);
        $this->assertCount(2, $data['questoes']);
        $this->assertSame($this->userA->id, $this->fetch('Quizes', $data['id'])->usuario_id);
    }

    public function test_criar_quiz_com_questao_invalida_nao_cria_nada(): void
    {
        $invalida = $this->multipla();
        $invalida['alternativas'][0]['correta'] = true;

        $this->postJson('/quizes', ['titulo' => 'Não deve existir', 'questoes' => [$invalida]]);

        $this->assertResponseCode(422);
        $this->assertArrayHasKey('questoes.0.alternativas', $this->responseJson()['errors']);
        $this->assertSame(0, $this->tabela('Quizes')->count());
    }

    public function test_total_de_questoes_nao_e_definido_pelo_corpo(): void
    {
        $this->postJson('/quizes', ['titulo' => 'Sem questões', 'total_questoes' => 10]);
        $this->assertResponseCode(201);
        $id = $this->responseJson()['data']['id'];
        $this->assertSame(0, $this->fetch('Quizes', $id)->total_questoes);

        $this->putJson("/quizes/{$id}", ['titulo' => 'Renomeado', 'total_questoes' => 7]);
        $this->assertResponseOk();
        $quiz = $this->fetch('Quizes', $id);
        $this->assertSame('Renomeado', $quiz->titulo);
        $this->assertSame(0, $quiz->total_questoes);
    }

    // ----------------------------------------------------------------- correção

    public function test_verifica_resposta_no_servidor(): void
    {
        $quiz = $this->quiz($this->userA);
        $questao = $this->questao($quiz, $this->multipla());

        $this->postJson("/quizes/{$quiz->id}/questoes/{$questao['id']}/verificar", ['alternativa_id' => $this->idErrada($questao)]);
        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertFalse($data['correta']);
        $this->assertSame($this->idCorreta($questao), $data['alternativa_correta_id']);
        $this->assertSame('Paris é a capital.', $data['explicacao']);

        $this->postJson("/quizes/{$quiz->id}/questoes/{$questao['id']}/verificar", ['alternativa_id' => $this->idCorreta($questao)]);
        $this->assertTrue($this->responseJson()['data']['correta']);

        // Verificar não grava nada
        $this->assertSame(0, $this->tabela('RespostasUsuario')->count());
    }

    public function test_completar_ignora_maiusculas_acentos_e_espacos(): void
    {
        $quiz = $this->quiz($this->userA);
        $questao = $this->questao($quiz, $this->completar('São Paulo'));
        $url = "/quizes/{$quiz->id}/questoes/{$questao['id']}/verificar";

        $this->postJson($url, ['resposta' => '  sao   PAULO ']);
        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertTrue($data['correta']);
        $this->assertSame('São Paulo', $data['resposta_esperada']);

        $this->postJson($url, ['resposta' => 'Rio de Janeiro']);
        $this->assertFalse($this->responseJson()['data']['correta']);

        $this->postJson($url, ['resposta' => '']);
        $data = $this->responseJson()['data'];
        $this->assertFalse($data['correta']);
        $this->assertFalse($data['respondida']);
    }

    public function test_alternativa_de_outra_questao_conta_como_errada(): void
    {
        $quiz = $this->quiz($this->userA);
        $q1 = $this->questao($quiz, $this->multipla('Q1'));
        $q2 = $this->questao($quiz, $this->multipla('Q2'));

        $this->postJson("/quizes/{$quiz->id}/questoes/{$q1['id']}/verificar", ['alternativa_id' => $this->idCorreta($q2)]);

        $this->assertResponseOk();
        $this->assertFalse($this->responseJson()['data']['correta']);
    }

    public function test_finalizar_calcula_acertos_grava_respostas_e_progresso(): void
    {
        $quiz = $this->quiz($this->userA);
        $q1 = $this->questao($quiz, $this->multipla('Q1'));
        $q2 = $this->questao($quiz, $this->multipla('Q2'));
        $q3 = $this->questao($quiz, $this->completar('São Paulo'));
        $this->questao($quiz, $this->completar('Brasília')); // sem resposta: conta como errada

        $this->postJson("/quizes/{$quiz->id}/finalizar", [
            'respostas' => [
                ['questao_id' => $q1['id'], 'alternativa_id' => $this->idCorreta($q1)],
                ['questao_id' => $q2['id'], 'alternativa_id' => $this->idErrada($q2)],
                ['questao_id' => $q3['id'], 'resposta' => 'SAO PAULO'],
                // Questão de outro quiz é ignorada
                ['questao_id' => 999999, 'alternativa_id' => 1],
            ],
        ]);

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertSame(4, $data['total']);
        $this->assertSame(2, $data['acertos']);
        $this->assertSame(2, $data['erros']);
        $this->assertSame(50, $data['percentual']);
        $this->assertSame([true, false, true, false], array_column($data['correcao'], 'correta'));
        $this->assertSame($this->idCorreta($q2), $data['correcao'][1]['alternativa_correta_id']);

        $respostas = $this->tabela('RespostasUsuario')
            ->where(['usuario_id' => $this->userA->id, 'tipo' => 'quiz', 'referencia_id' => $quiz->id]);
        $this->assertSame(4, $respostas->count());
        $this->assertSame(2, (clone $respostas)->where(['correto' => true])->count());

        $progresso = $this->tabela('ProgressoUsuario')
            ->where(['usuario_id' => $this->userA->id])->first();
        $this->assertSame(1, $progresso->quizes_concluidos);

        $this->get('/progresso/usuario/'.$this->userA->id);
        $this->assertSame(1, $this->responseJson()['data']['quizes_concluidos']);
    }

    public function test_gabaritar_o_quiz_da_a_conquista(): void
    {
        $quiz = $this->quiz($this->userA);
        $q1 = $this->questao($quiz, $this->multipla('Q1'));

        // Errando, não ganha
        $this->postJson("/quizes/{$quiz->id}/finalizar", ['respostas' => [['questao_id' => $q1['id'], 'alternativa_id' => $this->idErrada($q1)]]]);
        $this->assertSame(0, $this->tabela('ConquistasUsuario')->where('codigo', 'quiz_gabaritado')->count());

        $this->postJson("/quizes/{$quiz->id}/finalizar", ['respostas' => [['questao_id' => $q1['id'], 'alternativa_id' => $this->idCorreta($q1)]]]);
        $this->assertResponseOk();
        $this->assertSame(1, $this->tabela('ConquistasUsuario')->where(['usuario_id' => $this->userA->id, 'codigo' => 'quiz_gabaritado'])->count());
    }

    public function test_finalizar_de_novo_nao_conta_o_quiz_outra_vez(): void
    {
        $quiz = $this->quiz($this->userA);
        $q1 = $this->questao($quiz, $this->multipla('Q1'));
        $respostas = ['respostas' => [['questao_id' => $q1['id'], 'alternativa_id' => $this->idCorreta($q1)]]];

        $this->postJson("/quizes/{$quiz->id}/finalizar", $respostas);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['data']['primeira_conclusao']);

        $this->postJson("/quizes/{$quiz->id}/finalizar", $respostas);
        $this->assertResponseOk();
        $this->assertFalse($this->responseJson()['data']['primeira_conclusao']);

        $progresso = $this->tabela('ProgressoUsuario')
            ->where(['usuario_id' => $this->userA->id])->firstOrFail();
        $this->assertSame(1, $progresso->quizes_concluidos);
        // As respostas das duas tentativas continuam registradas
        $this->assertSame(2, $this->tabela('RespostasUsuario')
            ->where(['usuario_id' => $this->userA->id, 'referencia_id' => $quiz->id])->count());
    }

    public function test_finalizar_quiz_sem_questoes_responde422(): void
    {
        $quiz = $this->quiz($this->userA);

        $this->postJson("/quizes/{$quiz->id}/finalizar", ['respostas' => []]);

        $this->assertResponseCode(422);
        $this->assertSame(0, $this->tabela('ProgressoUsuario')->count());
    }

    // --------------------------------------------------------------------- IDOR

    public function test_quiz_privado_de_outro_usuario_nas_rotas_de_questoes_responde404(): void
    {
        $quiz = $this->quiz($this->userB);
        $questao = $this->questao($quiz, $this->multipla());
        $base = "/quizes/{$quiz->id}/questoes";

        $this->get($base);
        $this->assertResponseCode(404);
        $this->assertArrayNotHasKey('data', $this->responseJson());

        $this->postJson($base, $this->multipla('Invasora'));
        $this->assertResponseCode(404);

        $this->putJson($base, ['questoes' => [$this->multipla('Invasora')]]);
        $this->assertResponseCode(404);

        $this->putJson("{$base}/{$questao['id']}", ['enunciado' => 'Alterada']);
        $this->assertResponseCode(404);

        $this->delete("{$base}/{$questao['id']}");
        $this->assertResponseCode(404);

        $this->postJson("{$base}/{$questao['id']}/verificar", ['alternativa_id' => $this->idCorreta($questao)]);
        $this->assertResponseCode(404);

        $this->postJson("/quizes/{$quiz->id}/finalizar", ['respostas' => []]);
        $this->assertResponseCode(404);

        $atual = $this->fetch('QuizQuestoes', $questao['id']);
        $this->assertSame('Capital da França?', $atual->enunciado);
        $this->assertSame(1, $this->tabela('QuizQuestoes')->count());
        $this->assertSame(0, $this->tabela('RespostasUsuario')->count());
    }

    public function test_questao_de_outro_quiz_pelo_caminho_do_meu_quiz_responde404(): void
    {
        $meu = $this->quiz($this->userA);
        $alheio = $this->quiz($this->userB);
        $questaoAlheia = $this->questao($alheio, $this->multipla());
        $caminho = "/quizes/{$meu->id}/questoes/{$questaoAlheia['id']}";

        $this->putJson($caminho, ['enunciado' => 'Alterada']);
        $this->assertResponseCode(404);

        $this->delete($caminho);
        $this->assertResponseCode(404);

        $this->postJson("{$caminho}/verificar", ['alternativa_id' => $this->idCorreta($questaoAlheia)]);
        $this->assertResponseCode(404);

        $this->assertSame('Capital da França?', $this->fetch('QuizQuestoes', $questaoAlheia['id'])->enunciado);
    }

    public function test_quiz_publico_de_outro_usuario_pode_ser_jogado_mas_nao_editado(): void
    {
        $quiz = $this->quiz($this->userB, ['publico' => true]);
        $questao = $this->questao($quiz, $this->multipla());

        $this->get("/quizes/{$quiz->id}");
        $this->assertResponseOk();
        $this->assertArrayNotHasKey('correta', $this->responseJson()['data']['questoes'][0]['alternativas'][0]);

        $this->postJson("/quizes/{$quiz->id}/questoes/{$questao['id']}/verificar", ['alternativa_id' => $this->idCorreta($questao)]);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['data']['correta']);

        $this->postJson("/quizes/{$quiz->id}/finalizar", [
            'respostas' => [['questao_id' => $questao['id'], 'alternativa_id' => $this->idCorreta($questao)]],
        ]);
        $this->assertResponseOk();
        $this->assertSame(1, $this->responseJson()['data']['acertos']);

        // Gabarito e edição continuam só do dono
        $this->get("/quizes/{$quiz->id}/questoes");
        $this->assertResponseCode(404);

        $this->postJson("/quizes/{$quiz->id}/questoes", $this->multipla('Invasora'));
        $this->assertResponseCode(404);
        $this->assertSame(1, $this->fetch('Quizes', $quiz->id)->total_questoes);
    }

    // -------------------------------------------------------------- gerar quiz

    private function flashcards(Model $dono, int $quantidade, array $data = []): array
    {
        $criados = [];
        for ($i = 1; $i <= $quantidade; $i++) {
            $criados[] = $this->insert('Flashcards', $data + [
                'usuario_id' => $dono->id,
                'frente' => "Pergunta {$dono->id}-{$i}",
                'verso' => "Resposta {$dono->id}-{$i}",
                'nivel_dificuldade' => 'iniciante',
            ]);
        }

        return $criados;
    }

    public function test_gerar_quiz_dos_flashcards(): void
    {
        $meus = $this->flashcards($this->userA, 5);
        $this->flashcards($this->userB, 5);
        $versoPorFrente = [];
        foreach ($meus as $flashcard) {
            $versoPorFrente[$flashcard->frente] = $flashcard->verso;
        }

        $this->postJson('/quizes/gerar', ['quantidade' => 3, 'titulo' => 'Revisão de vocabulário']);

        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame('Revisão de vocabulário', $data['titulo']);
        $this->assertSame('flashcards', $data['tipo_criacao']);
        $this->assertSame(3, $data['total_questoes']);
        $this->assertCount(3, $data['questoes']);

        foreach ($data['questoes'] as $questao) {
            $this->assertSame('multipla_escolha', $questao['tipo']);
            $this->assertArrayHasKey($questao['enunciado'], $versoPorFrente);
            $this->assertCount(4, $questao['alternativas']);

            $corretas = array_values(array_filter($questao['alternativas'], fn ($a) => $a['correta']));
            $this->assertCount(1, $corretas);
            $this->assertSame($versoPorFrente[$questao['enunciado']], $corretas[0]['texto']);

            // Distratores só dos flashcards do próprio usuário, todos diferentes
            $textos = array_column($questao['alternativas'], 'texto');
            $this->assertSame($textos, array_values(array_unique($textos)));
            foreach ($textos as $texto) {
                $this->assertContains($texto, $versoPorFrente);
            }
        }

        $quiz = $this->fetch('Quizes', $data['id']);
        $this->assertSame($this->userA->id, $quiz->usuario_id);
    }

    public function test_gerar_com_poucos_flashcards_responde422(): void
    {
        $this->flashcards($this->userA, 3);
        $this->flashcards($this->userB, 10);

        $this->postJson('/quizes/gerar', ['quantidade' => 5]);

        $this->assertResponseCode(422);
        $body = $this->responseJson();
        $this->assertStringContainsString('pelo menos 4 flashcards', $body['message']);
        $this->assertArrayHasKey('flashcards', $body['errors']);
        $this->assertSame(0, $this->tabela('Quizes')->count());
    }

    public function test_gerar_filtra_por_nivel_e_por_tag(): void
    {
        $iniciantes = $this->flashcards($this->userA, 4);
        $avancado = $this->insert('Flashcards', [
            'usuario_id' => $this->userA->id,
            'frente' => 'Pergunta avançada',
            'verso' => 'Resposta avançada',
            'nivel_dificuldade' => 'avancado',
        ]);

        $this->postJson('/quizes/gerar', ['quantidade' => 10, 'nivel' => 'avancado']);
        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame(['Pergunta avançada'], array_column($data['questoes'], 'enunciado'));
        $this->assertSame('avancado', $data['nivel_dificuldade']);

        $tag = $this->insert('Tags', ['criado_por' => $this->userA->id, 'nome' => 'viagem']);
        $this->insert('FlashcardTags', ['flashcard_id' => $iniciantes[0]->id, 'tag_id' => $tag->id]);
        $this->insert('FlashcardTags', ['flashcard_id' => $avancado->id, 'tag_id' => $tag->id]);

        $this->postJson('/quizes/gerar', ['quantidade' => 10, 'tag_id' => $tag->id]);
        $this->assertResponseCode(201);
        $enunciados = array_column($this->responseJson()['data']['questoes'], 'enunciado');
        $this->assertEqualsCanonicalizing(['Pergunta avançada', $iniciantes[0]->frente], $enunciados);

        $this->postJson('/quizes/gerar', ['nivel' => 'intermediario']);
        $this->assertResponseCode(422);
    }

    public function test_gerar_com_tag_de_outro_usuario_responde404(): void
    {
        $this->flashcards($this->userA, 5);
        $tagAlheia = $this->insert('Tags', ['criado_por' => $this->userB->id, 'nome' => 'alheia']);

        $this->postJson('/quizes/gerar', ['tag_id' => $tagAlheia->id]);

        $this->assertResponseCode(404);
        $this->assertSame(0, $this->tabela('Quizes')->count());
    }

    public function test_gerar_valida_os_parametros(): void
    {
        $this->flashcards($this->userA, 5);

        $this->postJson('/quizes/gerar', ['quantidade' => 0, 'nivel' => 'expert', 'tag_id' => 'abc']);

        $this->assertResponseCode(422);
        $errors = $this->responseJson()['errors'];
        $this->assertArrayHasKey('quantidade', $errors);
        $this->assertArrayHasKey('nivel', $errors);
        $this->assertArrayHasKey('tag_id', $errors);

        $this->postJson('/quizes/gerar', ['quantidade' => 51]);
        $this->assertResponseCode(422);
    }
}

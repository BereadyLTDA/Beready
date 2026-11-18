<?php

namespace App\Http\Controllers;

use App\Services\QuizGeradorService;
use App\Services\QuizQuestaoService;
use App\Services\QuizService;
use App\Services\QuizTentativaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizesController extends Controller
{
    public function __construct(
        private QuizService $quizService,
        private QuizQuestaoService $questaoService,
        private QuizGeradorService $geradorService,
        private QuizTentativaService $tentativaService,
    ) {}

    // GET /quizes
    public function index(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->quizService->getQuizzesByUsuario($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar quizzes');
        }
    }

    // GET /quizes/{id} — inclui as questões SEM gabarito (formato de quem vai jogar)
    public function view(string $id): JsonResponse
    {
        try {
            $quiz = $this->findPlayableQuiz((int) $id);
            $quiz['questoes'] = $this->questaoService->listar((int) $id, false);

            return $this->jsonSuccess($quiz);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /quizes  (aceita `questoes` opcionais: tudo é validado antes de gravar)
    public function add(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $questoes = array_key_exists('questoes', $data)
                ? $this->questaoService->validarLista($data['questoes'])
                : [];

            $quiz = DB::transaction(function () use ($data, $questoes) {
                $quiz = $this->quizService->createQuiz($this->currentUserId(), $data);
                if ($questoes) {
                    $this->questaoService->inserirLista((int) $quiz['id'], $questoes);
                    $quiz = $this->quizService->getQuizById((int) $quiz['id']);
                }

                return $quiz;
            });
            $quiz['questoes'] = $this->questaoService->listar((int) $quiz['id'], true);

            return $this->jsonSuccess($quiz, 'Quiz criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar quiz');
        }
    }

    // POST /quizes/gerar  { quantidade?, nivel?, tag_id?, titulo? } — quiz a partir dos flashcards do usuário
    public function gerar(Request $request): JsonResponse
    {
        try {
            $quiz = $this->geradorService->gerar($this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($quiz, 'Quiz gerado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao gerar quiz');
        }
    }

    // PUT /quizes/{id}
    public function edit(Request $request, string $id): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $quiz = $this->quizService->updateQuiz((int) $id, $this->requestData($request));

            return $this->jsonSuccess($quiz, 'Quiz atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar quiz');
        }
    }

    // DELETE /quizes/{id}
    public function delete(string $id): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $this->quizService->deleteQuiz((int) $id);

            return $this->jsonSuccess(null, 'Quiz excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir quiz');
        }
    }

    // ------------------------------------------------------------------ questões (dono)

    // GET /quizes/{id}/questoes — COM gabarito, para o editor
    public function questoes(string $id): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);

            return $this->jsonSuccess($this->questaoService->listar((int) $id, true));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar questões');
        }
    }

    // PUT /quizes/{id}/questoes  { questoes: [...] } — substitui todas (ordem = posição)
    public function substituirQuestoes(Request $request, string $id): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $questoes = $this->questaoService->substituirTodas((int) $id, $this->requestData($request)['questoes'] ?? null);

            return $this->jsonSuccess($questoes, 'Questões salvas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar questões');
        }
    }

    // POST /quizes/{id}/questoes
    public function addQuestao(Request $request, string $id): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $questao = $this->questaoService->criar((int) $id, $this->requestData($request));

            return $this->jsonSuccess($questao, 'Questão criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar questão');
        }
    }

    // PUT /quizes/{id}/questoes/{questaoId}
    public function editQuestao(Request $request, string $id, string $questaoId): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $questao = $this->questaoService->atualizar((int) $id, (int) $questaoId, $this->requestData($request));

            return $this->jsonSuccess($questao, 'Questão atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar questão');
        }
    }

    // DELETE /quizes/{id}/questoes/{questaoId}
    public function deleteQuestao(string $id, string $questaoId): JsonResponse
    {
        try {
            $this->findOwnedQuiz((int) $id);
            $this->questaoService->excluir((int) $id, (int) $questaoId);

            return $this->jsonSuccess(null, 'Questão excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir questão');
        }
    }

    // ------------------------------------------------------------------ jogar (dono ou quiz público)

    // POST /quizes/{id}/questoes/{questaoId}/verificar  { alternativa_id } | { resposta }
    public function verificar(Request $request, string $id, string $questaoId): JsonResponse
    {
        try {
            $this->findPlayableQuiz((int) $id);
            $correcao = $this->tentativaService->verificar((int) $id, (int) $questaoId, $this->requestData($request));

            return $this->jsonSuccess($correcao);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao verificar resposta');
        }
    }

    // POST /quizes/{id}/finalizar  { respostas: [{ questao_id, alternativa_id | resposta }] }
    public function finalizar(Request $request, string $id): JsonResponse
    {
        try {
            $this->findPlayableQuiz((int) $id);
            $resultado = $this->tentativaService->finalizar(
                $this->currentUserId(),
                (int) $id,
                $this->requestData($request)['respostas'] ?? null,
            );

            return $this->jsonSuccess($resultado, 'Tentativa registrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao finalizar quiz');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findOwnedQuiz(int $id): array
    {
        $quiz = $this->quizService->getQuizById($id);
        if (! $this->canAccessUser((int) $quiz['usuario_id'])) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }

        return $quiz;
    }

    /**
     * Dono, admin ou quiz público.
     *
     * @return array<string, mixed>
     */
    private function findPlayableQuiz(int $id): array
    {
        $quiz = $this->quizService->getQuizById($id);
        if (empty($quiz['publico']) && ! $this->canAccessUser((int) $quiz['usuario_id'])) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }

        return $quiz;
    }
}

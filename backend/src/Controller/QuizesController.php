<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\PermissionService;
use App\Services\QuizGeradorService;
use App\Services\QuizQuestaoService;
use App\Services\QuizService;
use App\Services\QuizTentativaService;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;

class QuizesController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private QuizService $quizService,
        private QuizQuestaoService $questaoService,
        private QuizGeradorService $geradorService,
        private QuizTentativaService $tentativaService,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /quizes
    public function index()
    {
        try {
            $quizzes = $this->quizService->getQuizzesByUsuario($this->currentUserId());
            return $this->jsonSuccess($quizzes);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar quizzes');
        }
    }

    // GET /quizes/view/{id} — inclui as questões SEM gabarito (formato de quem vai jogar)
    public function view($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');

        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }

        try {
            $quiz = $this->findPlayableQuiz((int)$quizId);
            $quiz['questoes'] = $this->questaoService->listar((int)$quizId, false);

            return $this->jsonSuccess($quiz);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /quizes  (aceita `questoes` opcionais: tudo é validado antes de gravar)
    public function add()
    {
        $data = $this->getRequestData();

        try {
            $questoes = array_key_exists('questoes', $data)
                ? $this->questaoService->validarLista($data['questoes'])
                : [];

            $quiz = ConnectionManager::get('default')->transactional(function () use ($data, $questoes) {
                $quiz = $this->quizService->createQuiz($this->currentUserId(), $data);
                if ($questoes) {
                    $this->questaoService->inserirLista((int)$quiz['id'], $questoes);
                    $quiz = $this->quizService->getQuizById((int)$quiz['id']);
                }

                return $quiz;
            });
            $quiz['questoes'] = $this->questaoService->listar((int)$quiz['id'], true);

            return $this->jsonSuccess($quiz, 'Quiz criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar quiz');
        }
    }

    // POST /quizes/gerar  { quantidade?, nivel?, tag_id?, titulo? } — quiz a partir dos flashcards do usuário
    public function gerar()
    {
        try {
            $quiz = $this->geradorService
                ->gerar($this->currentUserId(), $this->getRequestData());

            return $this->jsonSuccess($quiz, 'Quiz gerado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao gerar quiz');
        }
    }

    // PUT /quizes/{id}
    public function edit($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');

        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }

        try {
            $this->findOwnedQuiz((int)$quizId);
            $quiz = $this->quizService->updateQuiz((int)$quizId, $this->getRequestData());
            return $this->jsonSuccess($quiz, 'Quiz atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar quiz');
        }
    }

    // DELETE /quizes/{id}
    public function delete($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');

        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }

        try {
            $this->findOwnedQuiz((int)$quizId);
            $this->quizService->deleteQuiz((int)$quizId);
            return $this->jsonSuccess(null, 'Quiz excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir quiz');
        }
    }

    // ------------------------------------------------------------------ questões (dono)

    // GET /quizes/{id}/questoes — COM gabarito, para o editor
    public function questoes($id = null)
    {
        try {
            $this->findOwnedQuiz((int)$id);
            return $this->jsonSuccess($this->questaoService->listar((int)$id, true));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar questões');
        }
    }

    // PUT /quizes/{id}/questoes  { questoes: [...] } — substitui todas (ordem = posição)
    public function substituirQuestoes($id = null)
    {
        try {
            $this->findOwnedQuiz((int)$id);
            $questoes = $this->questaoService->substituirTodas((int)$id, $this->getRequestData()['questoes'] ?? null);
            return $this->jsonSuccess($questoes, 'Questões salvas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar questões');
        }
    }

    // POST /quizes/{id}/questoes
    public function addQuestao($id = null)
    {
        try {
            $this->findOwnedQuiz((int)$id);
            $questao = $this->questaoService->criar((int)$id, $this->getRequestData());
            return $this->jsonSuccess($questao, 'Questão criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar questão');
        }
    }

    // PUT /quizes/{id}/questoes/{questaoId}
    public function editQuestao($id = null, $questaoId = null)
    {
        try {
            $this->findOwnedQuiz((int)$id);
            $questao = $this->questaoService->atualizar((int)$id, (int)$questaoId, $this->getRequestData());
            return $this->jsonSuccess($questao, 'Questão atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar questão');
        }
    }

    // DELETE /quizes/{id}/questoes/{questaoId}
    public function deleteQuestao($id = null, $questaoId = null)
    {
        try {
            $this->findOwnedQuiz((int)$id);
            $this->questaoService->excluir((int)$id, (int)$questaoId);
            return $this->jsonSuccess(null, 'Questão excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir questão');
        }
    }

    // ------------------------------------------------------------------ jogar (dono ou quiz público)

    // POST /quizes/{id}/questoes/{questaoId}/verificar  { alternativa_id } | { resposta }
    public function verificar($id = null, $questaoId = null)
    {
        try {
            $this->findPlayableQuiz((int)$id);
            $correcao = $this->tentativaService
                ->verificar((int)$id, (int)$questaoId, $this->getRequestData());
            return $this->jsonSuccess($correcao);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao verificar resposta');
        }
    }

    // POST /quizes/{id}/finalizar  { respostas: [{ questao_id, alternativa_id | resposta }] }
    public function finalizar($id = null)
    {
        try {
            $this->findPlayableQuiz((int)$id);
            $resultado = $this->tentativaService
                ->finalizar($this->currentUserId(), (int)$id, $this->getRequestData()['respostas'] ?? null);
            return $this->jsonSuccess($resultado, 'Tentativa registrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao finalizar quiz');
        }
    }

    /**
     * Quiz de outro usuário responde 404 (admin acessa todos).
     */
    private function findOwnedQuiz(int $id): array
    {
        $quiz = $this->quizService->getQuizById($id);
        if (!$this->canAccessUser((int)$quiz['usuario_id'])) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }

        return $quiz;
    }

    /**
     * Quiz público pode ser visto e jogado por qualquer usuário autenticado; privado, só pelo dono (ou admin).
     */
    private function findPlayableQuiz(int $id): array
    {
        $quiz = $this->quizService->getQuizById($id);
        if (empty($quiz['publico']) && !$this->canAccessUser((int)$quiz['usuario_id'])) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }

        return $quiz;
    }
}

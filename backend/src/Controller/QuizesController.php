<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\QuizService;
use App\Repositories\QuizRepository;

class QuizesController extends AppController
{
    private QuizService $quizService;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->quizService = new QuizService(new QuizRepository());
    }
    
    // GET /quizes
    public function index()
    {
        try {
            $quizzes = $this->quizService->getAllQuizzes();
            return $this->jsonSuccess($quizzes);
        } catch (\Exception $e) {
            return $this->jsonError('Erro ao carregar quizzes: ' . $e->getMessage(), 500);
        }
    }
    
    // GET /quizes/view/{id}
    public function view($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');
        
        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }
        
        try {
            $quiz = $this->quizService->getQuizById((int)$quizId);
            return $this->jsonSuccess($quiz);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // POST /quizes
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        // Log para debug
        error_log("=== QUIZ ADD ===");
        error_log("Dados recebidos: " . print_r($data, true));
        
        // Validar campos obrigatórios
        if (empty($data['usuario_id'])) {
            return $this->jsonError('ID do usuário é obrigatório', 400);
        }
        
        if (empty($data['titulo'])) {
            return $this->jsonError('Título é obrigatório', 400);
        }
        
        try {
            $quiz = $this->quizService->createQuiz($data);
            return $this->jsonSuccess($quiz, 'Quiz criado com sucesso', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            error_log("ERRO ao criar quiz: " . $e->getMessage());
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // PUT /quizes/{id}
    public function edit($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');
        
        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        error_log("=== QUIZ EDIT ===");
        error_log("ID: " . $quizId);
        error_log("Dados: " . print_r($data, true));
        
        try {
            $quiz = $this->quizService->updateQuiz((int)$quizId, $data);
            return $this->jsonSuccess($quiz, 'Quiz atualizado com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // DELETE /quizes/{id}
    public function delete($id = null)
    {
        $quizId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');
        
        if (!$quizId) {
            return $this->jsonError('ID do quiz não informado', 400);
        }
        
        error_log("=== QUIZ DELETE ===");
        error_log("ID: " . $quizId);
        
        try {
            $this->quizService->deleteQuiz((int)$quizId);
            return $this->jsonSuccess(null, 'Quiz excluído com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
}
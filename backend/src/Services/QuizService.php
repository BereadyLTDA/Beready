<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\QuizRepositoryInterface;

class QuizService
{
    private QuizRepositoryInterface $quizRepository;
    
    public function __construct(QuizRepositoryInterface $quizRepository)
    {
        $this->quizRepository = $quizRepository;
    }
    
    public function getAllQuizzes(): array
    {
        return $this->quizRepository->findAll();
    }
    
    public function getQuizById(int $id): array
    {
        $quiz = $this->quizRepository->findById($id);
        if (!$quiz) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }
        return $quiz;
    }
    
    public function createQuiz(array $data): array
    {
        error_log("=== QUIZ SERVICE CREATE ===");
        error_log("Dados recebidos: " . print_r($data, true));
        
        // Validações
        if (empty($data['usuario_id'])) {
            throw new \InvalidArgumentException('ID do usuário é obrigatório');
        }
        
        if (empty($data['titulo'])) {
            throw new \InvalidArgumentException('Título é obrigatório');
        }
        
        // Garantir valores padrão
        $data['tipo_criacao'] = $data['tipo_criacao'] ?? 'manual';
        $data['nivel_dificuldade'] = $data['nivel_dificuldade'] ?? 'iniciante';
        $data['total_questoes'] = (int)($data['total_questoes'] ?? 0);
        $data['publico'] = !empty($data['publico']);
        
        error_log("Dados após validação: " . print_r($data, true));
        
        $quiz = $this->quizRepository->create($data);
        return $quiz;
    }
    
    public function updateQuiz(int $id, array $data): array
    {
        $quiz = $this->quizRepository->findById($id);
        if (!$quiz) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }
        
        // Não permitir alterar usuario_id
        unset($data['usuario_id']);
        
        return $this->quizRepository->update($id, $data);
    }
    
    public function deleteQuiz(int $id): bool
    {
        $quiz = $this->quizRepository->findById($id);
        if (!$quiz) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }
        
        return $this->quizRepository->delete($id);
    }
}
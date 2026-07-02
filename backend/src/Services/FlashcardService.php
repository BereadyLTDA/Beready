<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\FlashcardRepositoryInterface;

class FlashcardService
{
    private FlashcardRepositoryInterface $flashcardRepository;
    
    public function __construct(FlashcardRepositoryInterface $flashcardRepository)
    {
        $this->flashcardRepository = $flashcardRepository;
    }
    
    public function getAllFlashcards(): array
    {
        return $this->flashcardRepository->findAll();
    }
    
    public function getFlashcardById(int $id): array
    {
        $flashcard = $this->flashcardRepository->findById($id);
        if (!$flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
        return $flashcard;
    }
    
    public function createFlashcard(array $data): array
    {
        if (empty($data['frente'])) {
            throw new \InvalidArgumentException('A pergunta (frente) é obrigatória');
        }
        
        if (empty($data['verso'])) {
            throw new \InvalidArgumentException('A resposta (verso) é obrigatória');
        }
        
        if (empty($data['usuario_id'])) {
            $data['usuario_id'] = 1;
        }
        
        $flashcard = $this->flashcardRepository->create($data);
        return $flashcard;
    }
    
    public function updateFlashcard(int $id, array $data): array
    {
        $flashcard = $this->flashcardRepository->findById($id);
        if (!$flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
        
        unset($data['usuario_id']);
        
        return $this->flashcardRepository->update($id, $data);
    }
    
    public function deleteFlashcard(int $id): bool
    {
        $flashcard = $this->flashcardRepository->findById($id);
        if (!$flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
        
        return $this->flashcardRepository->delete($id);
    }
}
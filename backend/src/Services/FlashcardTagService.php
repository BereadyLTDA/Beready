<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\FlashcardTagRepositoryInterface;

class FlashcardTagService
{
    private FlashcardTagRepositoryInterface $repository;
    
    public function __construct(FlashcardTagRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getTagsByFlashcard(int $flashcardId): array
    {
        return $this->repository->findByFlashcardId($flashcardId);
    }
    
    public function addTagToFlashcard(int $flashcardId, int $tagId): array
    {
        if ($this->repository->exists($flashcardId, $tagId)) {
            throw new \RuntimeException('Tag já associada a este flashcard', 409);
        }
        
        return $this->repository->create([
            'flashcard_id' => $flashcardId,
            'tag_id' => $tagId
        ]);
    }
    
    public function removeTagFromFlashcard(int $flashcardId, int $tagId): bool
    {
        if (!$this->repository->exists($flashcardId, $tagId)) {
            throw new \RuntimeException('Associação não encontrada', 404);
        }
        
        return $this->repository->deleteByFlashcardAndTag($flashcardId, $tagId);
    }
}
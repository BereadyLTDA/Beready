<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FlashcardRepositoryInterface;
use Cake\ORM\TableRegistry;

class FlashcardRepository implements FlashcardRepositoryInterface
{
    private $flashcardsTable;
    
    public function __construct()
    {
        $this->flashcardsTable = TableRegistry::getTableLocator()->get('Flashcards');
    }
    
    public function findAll(): array
    {
        $flashcards = $this->flashcardsTable->find()
            ->select(['id', 'usuario_id', 'frente', 'verso', 'nivel_dificuldade', 'criado_em', 'atualizado_em'])
            ->orderBy(['criado_em' => 'DESC'])
            ->all();
        
        $result = [];
        foreach ($flashcards as $flashcard) {
            $data = $flashcard->toArray();
            $result[] = $data;
        }
        return $result;
    }
    
    public function findById(int $id): ?array
    {
        $flashcard = $this->flashcardsTable->get($id);
        return $flashcard ? $flashcard->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $flashcard = $this->flashcardsTable->newEntity($data);
        $this->flashcardsTable->saveOrFail($flashcard);
        return $flashcard->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $flashcard = $this->flashcardsTable->get($id);
        $flashcard = $this->flashcardsTable->patchEntity($flashcard, $data);
        $this->flashcardsTable->saveOrFail($flashcard);
        return $flashcard->toArray();
    }
    
    public function delete(int $id): bool
    {
        $flashcard = $this->flashcardsTable->get($id);
        return $this->flashcardsTable->delete($flashcard);
    }
}
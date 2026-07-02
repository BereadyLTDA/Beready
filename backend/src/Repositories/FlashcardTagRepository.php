<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FlashcardTagRepositoryInterface;
use Cake\ORM\TableRegistry;

class FlashcardTagRepository implements FlashcardTagRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('FlashcardTags');
    }
    
    public function findByFlashcardId(int $flashcardId): array
    {
        $relations = $this->table->find()
            ->where(['flashcard_id' => $flashcardId])
            ->contain(['Tags'])
            ->all();
        
        $result = [];
        foreach ($relations as $relation) {
            $data = $relation->toArray();
            if ($relation->has('tag')) {
                $data['tag'] = $relation->tag->toArray();
            }
            $result[] = $data;
        }
        return $result;
    }
    
    public function findByTagId(int $tagId): array
    {
        $relations = $this->table->find()
            ->where(['tag_id' => $tagId])
            ->all();
        return array_map(fn($r) => $r->toArray(), $relations->toArray());
    }
    
    public function create(array $data): array
    {
        $relation = $this->table->newEntity($data);
        $this->table->saveOrFail($relation);
        return $relation->toArray();
    }
    
    public function deleteByFlashcardAndTag(int $flashcardId, int $tagId): bool
    {
        $relation = $this->table->find()
            ->where(['flashcard_id' => $flashcardId, 'tag_id' => $tagId])
            ->first();
        
        if (!$relation) {
            return false;
        }
        
        return $this->table->delete($relation);
    }
    
    public function exists(int $flashcardId, int $tagId): bool
    {
        return $this->table->find()
            ->where(['flashcard_id' => $flashcardId, 'tag_id' => $tagId])
            ->count() > 0;
    }
}
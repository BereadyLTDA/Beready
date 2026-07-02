<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TagRepositoryInterface;
use Cake\ORM\TableRegistry;

class TagRepository implements TagRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('Tags');
    }
    
    public function findAll(): array
    {
        $tags = $this->table->find()->orderBy(['nome' => 'ASC'])->all();
        return array_map(fn($t) => $t->toArray(), $tags->toArray());
    }
    
    public function findById(int $id): ?array
    {
        try {
            $tag = $this->table->get($id);
            return $tag->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function findByUsuarioId(int $usuarioId): array
    {
        $tags = $this->table->find()
            ->where(['criado_por' => $usuarioId])
            ->orderBy(['nome' => 'ASC'])
            ->all();
        return array_map(fn($t) => $t->toArray(), $tags->toArray());
    }
    
    public function findByName(string $name): ?array
    {
        $tag = $this->table->find()->where(['nome' => $name])->first();
        return $tag ? $tag->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $tag = $this->table->newEntity($data);
        $this->table->saveOrFail($tag);
        return $tag->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $tag = $this->table->get($id);
        $tag = $this->table->patchEntity($tag, $data);
        $this->table->saveOrFail($tag);
        return $tag->toArray();
    }
    
    public function delete(int $id): bool
    {
        $tag = $this->table->get($id);
        return $this->table->delete($tag);
    }
}
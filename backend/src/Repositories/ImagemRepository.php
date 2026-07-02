<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ImagemRepositoryInterface;
use Cake\ORM\TableRegistry;

class ImagemRepository implements ImagemRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('ImagensGeradas');
    }
    
    public function findByPromptId(int $promptId): array
    {
        $imagens = $this->table->find()
            ->where(['prompt_id' => $promptId])
            ->orderBy(['criado_em' => 'DESC'])
            ->all();
        return array_map(fn($i) => $i->toArray(), $imagens->toArray());
    }
    
    public function findById(int $id): ?array
    {
        $imagem = $this->table->get($id);
        return $imagem ? $imagem->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $imagem = $this->table->newEntity($data);
        $this->table->saveOrFail($imagem);
        return $imagem->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $imagem = $this->table->get($id);
        $imagem = $this->table->patchEntity($imagem, $data);
        $this->table->saveOrFail($imagem);
        return $imagem->toArray();
    }
    
    public function delete(int $id): bool
    {
        $imagem = $this->table->get($id);
        return $this->table->delete($imagem);
    }
}
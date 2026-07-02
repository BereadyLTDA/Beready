<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\PromptRepositoryInterface;
use Cake\ORM\TableRegistry;

class PromptRepository implements PromptRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('Prompts');
    }
    
    public function findAll(): array
    {
        $prompts = $this->table->find()->orderBy(['criado_em' => 'DESC'])->all();
        return array_map(fn($p) => $p->toArray(), $prompts->toArray());
    }
    
    public function findById(int $id): ?array
    {
        $prompt = $this->table->get($id);
        return $prompt ? $prompt->toArray() : null;
    }
    
    public function findByUsuarioId(int $usuarioId): array
    {
        $prompts = $this->table->find()
            ->where(['usuario_id' => $usuarioId])
            ->orderBy(['criado_em' => 'DESC'])
            ->all();
        return array_map(fn($p) => $p->toArray(), $prompts->toArray());
    }
    
    public function create(array $data): array
    {
        $prompt = $this->table->newEntity($data);
        $this->table->saveOrFail($prompt);
        return $prompt->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $prompt = $this->table->get($id);
        $prompt = $this->table->patchEntity($prompt, $data);
        $this->table->saveOrFail($prompt);
        return $prompt->toArray();
    }
    
    public function delete(int $id): bool
    {
        $prompt = $this->table->get($id);
        return $this->table->delete($prompt);
    }
}
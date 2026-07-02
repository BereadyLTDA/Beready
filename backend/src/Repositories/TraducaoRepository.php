<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TraducaoRepositoryInterface;
use Cake\ORM\TableRegistry;

class TraducaoRepository implements TraducaoRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('Traducoes');
    }
    
    public function findByPromptId(int $promptId): array
    {
        $traducoes = $this->table->find()
            ->where(['prompt_id' => $promptId])
            ->orderBy(['criado_em' => 'DESC'])
            ->all();
        return array_map(fn($t) => $t->toArray(), $traducoes->toArray());
    }
    
    public function findById(int $id): ?array
    {
        $traducao = $this->table->get($id);
        return $traducao ? $traducao->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $traducao = $this->table->newEntity($data);
        $this->table->saveOrFail($traducao);
        return $traducao->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $traducao = $this->table->get($id);
        $traducao = $this->table->patchEntity($traducao, $data);
        $this->table->saveOrFail($traducao);
        return $traducao->toArray();
    }
    
    public function delete(int $id): bool
    {
        $traducao = $this->table->get($id);
        return $this->table->delete($traducao);
    }
}
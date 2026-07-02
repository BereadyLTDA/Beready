<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FraseRepositoryInterface;
use Cake\ORM\TableRegistry;

class FraseRepository implements FraseRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('FrasesSemelhantes');
    }
    
    public function findByPromptId(int $promptId): array
    {
        $frases = $this->table->find()
            ->where(['prompt_id' => $promptId])
            ->orderBy(['pontuacao_semelhante' => 'DESC'])
            ->all();
        return array_map(fn($f) => $f->toArray(), $frases->toArray());
    }
    
    public function findById(int $id): ?array
    {
        $frase = $this->table->get($id);
        return $frase ? $frase->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $frase = $this->table->newEntity($data);
        $this->table->saveOrFail($frase);
        return $frase->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $frase = $this->table->get($id);
        $frase = $this->table->patchEntity($frase, $data);
        $this->table->saveOrFail($frase);
        return $frase->toArray();
    }
    
    public function delete(int $id): bool
    {
        $frase = $this->table->get($id);
        return $this->table->delete($frase);
    }
}
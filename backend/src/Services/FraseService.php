<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\FraseRepositoryInterface;

class FraseService
{
    private FraseRepositoryInterface $repository;
    
    public function __construct(FraseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getFrasesByPrompt(int $promptId): array
    {
        return $this->repository->findByPromptId($promptId);
    }
    
    public function getFraseById(int $id): array
    {
        $frase = $this->repository->findById($id);
        if (!$frase) {
            throw new \RuntimeException('Frase não encontrada', 404);
        }
        return $frase;
    }
    
    public function createFrase(array $data): array
    {
        if (empty($data['prompt_id'])) {
            throw new \InvalidArgumentException('ID do prompt é obrigatório');
        }
        
        if (empty($data['frase_semelhante'])) {
            throw new \InvalidArgumentException('Frase semelhante é obrigatória');
        }
        
        $data['tipo_frase'] = $data['tipo_frase'] ?? 'relacionada';
        $data['nivel_dificuldade'] = $data['nivel_dificuldade'] ?? 'iniciante';
        $data['pontuacao_semelhante'] = $data['pontuacao_semelhante'] ?? 0;
        
        return $this->repository->create($data);
    }
    
    public function updateFrase(int $id, array $data): array
    {
        $frase = $this->repository->findById($id);
        if (!$frase) {
            throw new \RuntimeException('Frase não encontrada', 404);
        }
        
        return $this->repository->update($id, $data);
    }
    
    public function deleteFrase(int $id): bool
    {
        $frase = $this->repository->findById($id);
        if (!$frase) {
            throw new \RuntimeException('Frase não encontrada', 404);
        }
        
        return $this->repository->delete($id);
    }
}
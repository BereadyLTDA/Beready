<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\TraducaoRepositoryInterface;

class TraducaoService
{
    private TraducaoRepositoryInterface $repository;
    
    public function __construct(TraducaoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getTraducoesByPrompt(int $promptId): array
    {
        return $this->repository->findByPromptId($promptId);
    }
    
    public function getTraducaoById(int $id): array
    {
        $traducao = $this->repository->findById($id);
        if (!$traducao) {
            throw new \RuntimeException('Tradução não encontrada', 404);
        }
        return $traducao;
    }
    
    public function createTraducao(array $data): array
    {
        if (empty($data['prompt_id'])) {
            throw new \InvalidArgumentException('ID do prompt é obrigatório');
        }
        
        if (empty($data['texto_traduzido'])) {
            throw new \InvalidArgumentException('Texto traduzido é obrigatório');
        }
        
        $data['idioma_destino'] = $data['idioma_destino'] ?? 'en';
        $data['pontuacao_confianca'] = $data['pontuacao_confianca'] ?? 0;
        
        if (isset($data['traducoes_alternativas']) && is_array($data['traducoes_alternativas'])) {
            $data['traducoes_alternativas'] = json_encode($data['traducoes_alternativas']);
        }
        
        return $this->repository->create($data);
    }
    
    public function updateTraducao(int $id, array $data): array
    {
        $traducao = $this->repository->findById($id);
        if (!$traducao) {
            throw new \RuntimeException('Tradução não encontrada', 404);
        }
        
        if (isset($data['traducoes_alternativas']) && is_array($data['traducoes_alternativas'])) {
            $data['traducoes_alternativas'] = json_encode($data['traducoes_alternativas']);
        }
        
        return $this->repository->update($id, $data);
    }
    
    public function deleteTraducao(int $id): bool
    {
        $traducao = $this->repository->findById($id);
        if (!$traducao) {
            throw new \RuntimeException('Tradução não encontrada', 404);
        }
        
        return $this->repository->delete($id);
    }
}
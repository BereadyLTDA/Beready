<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\PromptRepositoryInterface;

class PromptService
{
    private PromptRepositoryInterface $repository;
    
    public function __construct(PromptRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getAllPrompts(): array
    {
        return $this->repository->findAll();
    }
    
    public function getPromptById(int $id): array
    {
        $prompt = $this->repository->findById($id);
        if (!$prompt) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }
        return $prompt;
    }
    
    public function getPromptsByUsuario(int $usuarioId): array
    {
        return $this->repository->findByUsuarioId($usuarioId);
    }
    
    public function createPrompt(array $data): array
    {
        if (empty($data['texto_original'])) {
            throw new \InvalidArgumentException('Texto original é obrigatório');
        }
        
        if (empty($data['usuario_id'])) {
            throw new \InvalidArgumentException('ID do usuário é obrigatório');
        }
        
        $data['contexto'] = $data['contexto'] ?? 'manual';
        $data['idioma_original'] = $data['idioma_original'] ?? 'pt-BR';
        
        return $this->repository->create($data);
    }
    
    public function updatePrompt(int $id, array $data): array
    {
        $prompt = $this->repository->findById($id);
        if (!$prompt) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }
        
        return $this->repository->update($id, $data);
    }
    
    public function deletePrompt(int $id): bool
    {
        $prompt = $this->repository->findById($id);
        if (!$prompt) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }
        
        return $this->repository->delete($id);
    }
}
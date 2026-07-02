<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\TagRepositoryInterface;

class TagService
{
    private TagRepositoryInterface $repository;
    
    public function __construct(TagRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getAllTags(): array
    {
        return $this->repository->findAll();
    }
    
    public function getTagById(int $id): array
    {
        $tag = $this->repository->findById($id);
        if (!$tag) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }
        return $tag;
    }
    
    public function getTagsByUsuario(int $usuarioId): array
    {
        return $this->repository->findByUsuarioId($usuarioId);
    }
    
    public function createTag(array $data): array
    {
        if (empty($data['nome'])) {
            throw new \InvalidArgumentException('Nome da tag é obrigatório');
        }
        
        if (empty($data['criado_por'])) {
            throw new \InvalidArgumentException('ID do criador é obrigatório');
        }
        
        // Verifica se tag já existe
        $existing = $this->repository->findByName($data['nome']);
        if ($existing) {
            throw new \RuntimeException('Tag já existe', 409);
        }
        
        return $this->repository->create($data);
    }
    
    public function updateTag(int $id, array $data): array
    {
        $tag = $this->repository->findById($id);
        if (!$tag) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }
        
        // Verifica se novo nome já existe (se for diferente)
        if (isset($data['nome']) && $data['nome'] !== $tag['nome']) {
            $existing = $this->repository->findByName($data['nome']);
            if ($existing) {
                throw new \RuntimeException('Tag já existe', 409);
            }
        }
        
        return $this->repository->update($id, $data);
    }
    
    public function deleteTag(int $id): bool
    {
        $tag = $this->repository->findById($id);
        if (!$tag) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }
        
        return $this->repository->delete($id);
    }
}
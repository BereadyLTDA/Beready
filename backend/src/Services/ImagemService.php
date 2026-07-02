<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\ImagemRepositoryInterface;

class ImagemService
{
    private ImagemRepositoryInterface $repository;
    
    public function __construct(ImagemRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getImagensByPrompt(int $promptId): array
    {
        return $this->repository->findByPromptId($promptId);
    }
    
    public function getImagemById(int $id): array
    {
        $imagem = $this->repository->findById($id);
        if (!$imagem) {
            throw new \RuntimeException('Imagem não encontrada', 404);
        }
        return $imagem;
    }
    
    public function createImagem(array $data): array
    {
        if (empty($data['prompt_id'])) {
            throw new \InvalidArgumentException('ID do prompt é obrigatório');
        }
        
        if (empty($data['url_imagem'])) {
            throw new \InvalidArgumentException('URL da imagem é obrigatória');
        }
        
        $data['qualidade_imagem'] = $data['qualidade_imagem'] ?? 'media';
        $data['servico_geracao'] = $data['servico_geracao'] ?? 'dalle';
        
        return $this->repository->create($data);
    }
    
    public function updateImagem(int $id, array $data): array
    {
        $imagem = $this->repository->findById($id);
        if (!$imagem) {
            throw new \RuntimeException('Imagem não encontrada', 404);
        }
        
        return $this->repository->update($id, $data);
    }
    
    public function deleteImagem(int $id): bool
    {
        $imagem = $this->repository->findById($id);
        if (!$imagem) {
            throw new \RuntimeException('Imagem não encontrada', 404);
        }
        
        return $this->repository->delete($id);
    }
}
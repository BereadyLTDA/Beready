<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\PreferenciaRepositoryInterface;

class PreferenciaService
{
    private PreferenciaRepositoryInterface $repository;
    
    public function __construct(PreferenciaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getByUsuarioId(int $usuarioId): array
    {
        $preferencias = $this->repository->findByUsuarioId($usuarioId);
        if (!$preferencias) {
            // Retorna preferências padrão
            return [
                'usuario_id' => $usuarioId,
                'tema' => 'claro',
                'modo_daltonico' => false,
                'notificacoes_ativas' => true,
                'som_ativo' => true,
                'traducao_automatica' => true,
                'preferencia_dificuldade' => 'adaptativo',
                'meta_diaria_minutos' => 30,
            ];
        }
        return $preferencias;
    }
    
    public function save(int $usuarioId, array $data): array
    {
        return $this->repository->createOrUpdate($usuarioId, $data);
    }
}
<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\PreferenciaRepositoryInterface;
use Cake\ORM\TableRegistry;

class PreferenciaRepository implements PreferenciaRepositoryInterface
{
    private $table;
    
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('PreferenciasUsuario');
    }
    
    public function findByUsuarioId(int $usuarioId): ?array
    {
        $preferencia = $this->table->find()
            ->where(['usuario_id' => $usuarioId])
            ->first();
        return $preferencia ? $preferencia->toArray() : null;
    }
    
    public function create(array $data): array
    {
        $entity = $this->table->newEntity($data);
        $this->table->saveOrFail($entity);
        return $entity->toArray();
    }
    
    public function update(int $usuarioId, array $data): array
    {
        $entity = $this->table->find()->where(['usuario_id' => $usuarioId])->first();
        if (!$entity) {
            throw new \RuntimeException('Preferências não encontradas', 404);
        }
        $entity = $this->table->patchEntity($entity, $data);
        $this->table->saveOrFail($entity);
        return $entity->toArray();
    }
    
    public function createOrUpdate(int $usuarioId, array $data): array
    {
        $data['usuario_id'] = $usuarioId;
        $existing = $this->findByUsuarioId($usuarioId);
        if ($existing) {
            return $this->update($usuarioId, $data);
        }
        return $this->create($data);
    }
}
<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use Cake\ORM\TableRegistry;

class UserRepository implements UserRepositoryInterface
{
    private $usersTable;
    
    public function __construct()
    {
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
    }
    
    public function findById(int $id): ?array
    {
        $user = $this->usersTable->find()
          ->select([
              'id',
              'nome',
              'email',
              'senha_hash',
              'role',
              'status',
              'telefone',
              'nivel_ingles',
              'idioma_preferido',
              'objetivos_aprendizado',
              'foto_perfil',
              'uuid',
              'criado_em',
              'atualizado_em',
              'ultimo_login'
          ])
          ->where(['id' => $id])
          ->first();
        
        if (!$user) {
            return null;
        }
        
        $data = $user->toArray();
        
        // Garantir que senha_hash está presente
        if (isset($user->senha_hash)) {
            $data['senha_hash'] = $user->senha_hash;
        }
        
        if (!isset($data['role']) || empty($data['role'])) {
            $data['role'] = 'user';
        }
        
        return $data;
    }
    
    public function findByEmail(string $email): ?array
    {
        $user = $this->usersTable->find()
            ->select(['id', 'nome', 'email', 'senha_hash', 'role', 'status', 'telefone', 'nivel_ingles', 'idioma_preferido', 'objetivos_aprendizado', 'uuid', 'criado_em', 'atualizado_em', 'ultimo_login'])
            ->where(['email' => $email])
            ->first();
        
        if (!$user) {
            return null;
        }
        
        $data = $user->toArray();
        
        if (isset($user->senha_hash)) {
            $data['senha_hash'] = $user->senha_hash;
        }
        
        if (!isset($data['role']) || empty($data['role'])) {
            $data['role'] = 'user';
        }
        
        return $data;
    }
    
    public function create(array $data): array
    {
        if (!isset($data['role'])) {
            $data['role'] = 'user';
        }
        
        $user = $this->usersTable->newEntity($data);
        $this->usersTable->saveOrFail($user);
        return $user->toArray();
    }
    
    public function update(int $id, array $data): array
    {
        $user = $this->usersTable->get($id);
        $user = $this->usersTable->patchEntity($user, $data);
        $this->usersTable->saveOrFail($user);
        return $user->toArray();
    }
    
    public function delete(int $id): bool
    {
        $user = $this->usersTable->get($id);
        return $this->usersTable->delete($user);
    }
    
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->usersTable->find()->where(['email' => $email]);
        if ($excludeId) {
            $query->where(['id !=' => $excludeId]);
        }
        return $query->count() > 0;
    }

    public function findByUuid(string $uuid): ?array
    {
        $user = $this->usersTable->find()
        ->select([
            'id',
            'nome',
            'email',
            'role',
            'status',
            'foto_perfil',
            'uuid'
        ])
        ->where(['uuid' => $uuid])
        ->first();
        return $user ? $user->toArray() : null;
    }

    public function findByResetToken(string $token): ?array
    {
        $user = $this->usersTable->find()
            ->select(['id', 'nome', 'email', 'role', 'reset_token', 'reset_token_expires'])
            ->where(['reset_token' => $token, 'reset_token_expires >' => date('Y-m-d H:i:s')])
            ->first();
        return $user ? $user->toArray() : null;
    }

    public function updateResetToken(int $id, ?string $token, ?string $expires): bool
    {
        $user = $this->usersTable->get($id);
        $user->reset_token = $token;
        $user->reset_token_expires = $expires;
        return (bool) $this->usersTable->save($user);
    }
}
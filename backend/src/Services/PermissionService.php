<?php

namespace App\Services;

use Cake\ORM\TableRegistry;
use Cake\Log\Log;

class PermissionService
{
    private $rolesTable;
    private $permissoesTable;
    private $rolePermissoesTable;
    private $usuarioRolesTable;
    private $assinaturasTable;
    private $planosTable;
    private $usersTable;

    public function __construct(private AssinaturaService $assinaturaService)
    {
        $this->rolesTable = TableRegistry::getTableLocator()->get('Roles');
        $this->permissoesTable = TableRegistry::getTableLocator()->get('Permissoes');
        $this->rolePermissoesTable = TableRegistry::getTableLocator()->get('RolePermissoes');
        $this->usuarioRolesTable = TableRegistry::getTableLocator()->get('UsuarioRoles');
        $this->assinaturasTable = TableRegistry::getTableLocator()->get('Assinaturas');
        $this->planosTable = TableRegistry::getTableLocator()->get('Planos');
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
    }

    /**
     * Obtém todas as permissões do sistema
     */
    public function getAllPermissions(): array
    {
        $permissoes = $this->permissoesTable->find()
            ->where(['is_ativo' => true])
            ->toArray();
        
        return array_map(function($perm) {
            return $perm->nome;
        }, $permissoes);
    }

    /**
     * Obtém todas as roles com permissões
     */
    public function getRolesWithPermissions(): array
    {
        return $this->rolesTable->getWithPermissions();
    }

    /**
     * Obtém permissões de um usuário
     */
    public function getUserPermissions(int $usuarioId): array
    {
        $permissions = [];

        // Se for admin, retorna todas as permissões
        try {
            $user = $this->usersTable->get($usuarioId);
            if ($user->role === 'admin') {
                return $this->getAllPermissions();
            }
        } catch (\Exception $e) {
            // Usuário não encontrado
            return [];
        }

        // 1. Verificar roles manuais
        $usuarioRoles = $this->usuarioRolesTable->find()
            ->contain(['Roles.Permissoes'])
            ->where([
                'usuario_id' => $usuarioId,
                'is_manual' => true,
            ])
            ->toArray();

        foreach ($usuarioRoles as $ur) {
            if ($ur->role && $ur->role->permissoes) {
                foreach ($ur->role->permissoes as $perm) {
                    $permissions[] = $perm->nome;
                }
            }
        }

        // 2. Verificar role da assinatura ativa (mesma regra de vigência do AssinaturaService,
        //    inclusive a carência da renovação automática do Stripe)
        $ativa = $this->assinaturaService->getAtiva($usuarioId);
        $assinatura = $ativa && $ativa->is_ativo
            ? $this->assinaturasTable->find()
                ->where(['Assinaturas.id' => $ativa->id])
                ->contain(['Planos.Roles.Permissoes'])
                ->first()
            : null;

        if ($assinatura && $assinatura->plano && $assinatura->plano->role) {
            foreach ($assinatura->plano->role->permissoes as $perm) {
                $permissions[] = $perm->nome;
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Verifica se usuário tem permissão
     */
    public function hasPermission(int $usuarioId, string $permission): bool
    {
        if ($this->isAdmin($usuarioId)) {
            return true;
        }

        $permissions = $this->getUserPermissions($usuarioId);
        return in_array($permission, $permissions);
    }

    /**
     * Verifica se usuário é admin
     */
    public function isAdmin(int $usuarioId): bool
    {
        try {
            $user = $this->usersTable->get($usuarioId);
            return $user->role === 'admin';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Atribui role a um usuário
     */
    public function assignRole(int $usuarioId, int $roleId, ?int $atribuidoPor = null): bool
    {
        $existing = $this->usuarioRolesTable->find()
            ->where([
                'usuario_id' => $usuarioId,
                'role_id' => $roleId,
            ])
            ->first();

        if ($existing) {
            return true;
        }

        $entity = $this->usuarioRolesTable->newEntity([
            'usuario_id' => $usuarioId,
            'role_id' => $roleId,
            'atribuido_por' => $atribuidoPor,
            'is_manual' => true,
        ]);

        return $this->usuarioRolesTable->save($entity) !== false;
    }

    /**
     * Remove role de um usuário
     */
    public function removeRole(int $usuarioId, int $roleId): bool
    {
        $entity = $this->usuarioRolesTable->find()
            ->where([
                'usuario_id' => $usuarioId,
                'role_id' => $roleId,
            ])
            ->first();

        if ($entity) {
            return $this->usuarioRolesTable->delete($entity) !== false;
        }

        return true;
    }

    /**
     * Cria nova role
     */
    public function createRole(array $data): array
    {
        $permissionIds = (array)($data['permission_ids'] ?? []);
        unset($data['permission_ids'], $data['is_sistema']);

        $role = $this->rolesTable->newEntity($data);

        $saved = $this->rolesTable->getConnection()->transactional(function () use ($role, $permissionIds) {
            if (!$this->rolesTable->save($role)) {
                return false;
            }
            $this->savePermissoes((int)$role->id, $permissionIds);

            return true;
        });

        if (!$saved) {
            return ['success' => false, 'errors' => $role->getErrors()];
        }

        return ['success' => true, 'data' => $this->rolesTable->get($role->id, contain: ['Permissoes'])];
    }

    /**
     * Atualiza role. "permission_ids" ausente mantém as permissões atuais.
     */
    public function updateRole(int $roleId, array $data): array
    {
        $permissionIds = array_key_exists('permission_ids', $data) ? (array)$data['permission_ids'] : null;
        unset($data['permission_ids'], $data['is_sistema']);

        $role = $this->rolesTable->patchEntity($this->rolesTable->get($roleId), $data);

        $saved = $this->rolesTable->getConnection()->transactional(function () use ($role, $roleId, $permissionIds) {
            if (!$this->rolesTable->save($role)) {
                return false;
            }
            if ($permissionIds !== null) {
                $this->rolePermissoesTable->deleteAll(['role_id' => $roleId]);
                $this->savePermissoes($roleId, $permissionIds);
            }

            return true;
        });

        if (!$saved) {
            return ['success' => false, 'errors' => $role->getErrors()];
        }

        return ['success' => true, 'data' => $this->rolesTable->get($roleId, contain: ['Permissoes'])];
    }

    /**
     * Insere os vínculos role_permissoes; falha lança exceção e desfaz a transação do chamador.
     */
    private function savePermissoes(int $roleId, array $permissionIds): void
    {
        foreach (array_unique(array_map('intval', $permissionIds)) as $permId) {
            $this->rolePermissoesTable->saveOrFail(
                $this->rolePermissoesTable->newEntity([
                    'role_id' => $roleId,
                    'permissao_id' => $permId,
                ])
            );
        }
    }

    /**
     * Exclui role (apenas se não for do sistema)
     */
    public function deleteRole(int $roleId): array
    {
        if ($this->rolesTable->isSistema($roleId)) {
            return ['success' => false, 'error' => 'Não é possível excluir uma role do sistema'];
        }

        $role = $this->rolesTable->get($roleId);
        
        if ($this->rolesTable->delete($role)) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    /**
     * Cria log de auditoria
     */
    public function logAudit(int $usuarioId, string $acao, string $entidade, int $entidadeId, $oldData, $newData): void
    {
        $logsTable = TableRegistry::getTableLocator()->get('LogsPermissoes');
        
        $log = $logsTable->newEntity([
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'dados_anteriores' => $oldData ? json_encode($oldData) : null,
            'dados_novos' => $newData ? json_encode($newData) : null,
        ]);
        
        $logsTable->save($log);
    }
}
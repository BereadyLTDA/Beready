<?php

namespace App\Services;

use App\Models\Assinatura;
use App\Models\LogPermissao;
use App\Models\Permissao;
use App\Models\Role;
use App\Models\User;
use App\Models\UsuarioRole;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(private AssinaturaService $assinaturaService) {}

    /**
     * Nomes de todas as permissões ativas do sistema.
     *
     * @return list<string>
     */
    public function getAllPermissions(): array
    {
        return Permissao::where('is_ativo', true)->pluck('nome')->all();
    }

    /**
     * Permissões do usuário: as das roles atribuídas manualmente + as da role do plano ativo.
     * Admin recebe todas.
     *
     * @return list<string>
     */
    public function getUserPermissions(int $usuarioId): array
    {
        $user = User::find($usuarioId);
        if (! $user) {
            return [];
        }
        if ($user->role === 'admin') {
            return $this->getAllPermissions();
        }

        $permissions = [];

        // 1. Roles manuais
        $usuarioRoles = UsuarioRole::with('role.permissoes')
            ->where('usuario_id', $usuarioId)
            ->where('is_manual', true)
            ->get();

        foreach ($usuarioRoles as $ur) {
            foreach ($ur->role?->permissoes ?? [] as $perm) {
                $permissions[] = $perm->nome;
            }
        }

        // 2. Role da assinatura ativa (mesma regra de vigência do AssinaturaService,
        //    inclusive a carência da renovação automática do Stripe)
        $ativa = $this->assinaturaService->getAtiva($usuarioId);
        $assinatura = $ativa && $ativa->is_ativo
            ? Assinatura::with('plano.role.permissoes')->find($ativa->id)
            : null;

        foreach ($assinatura?->plano?->role?->permissoes ?? [] as $perm) {
            $permissions[] = $perm->nome;
        }

        return array_values(array_unique($permissions));
    }

    public function hasPermission(int $usuarioId, string $permission): bool
    {
        if ($this->isAdmin($usuarioId)) {
            return true;
        }

        return in_array($permission, $this->getUserPermissions($usuarioId), true);
    }

    /**
     * Role consultada no banco (não no token), para refletir rebaixamentos na hora.
     */
    public function isAdmin(int $usuarioId): bool
    {
        return User::whereKey($usuarioId)->value('role') === 'admin';
    }

    public function assignRole(int $usuarioId, int $roleId, ?int $atribuidoPor = null): bool
    {
        $existe = UsuarioRole::where('usuario_id', $usuarioId)->where('role_id', $roleId)->exists();
        if ($existe) {
            return true;
        }

        UsuarioRole::create([
            'usuario_id' => $usuarioId,
            'role_id' => $roleId,
            'atribuido_por' => $atribuidoPor,
            'is_manual' => true,
        ]);

        return true;
    }

    public function removeRole(int $usuarioId, int $roleId): bool
    {
        UsuarioRole::where('usuario_id', $usuarioId)->where('role_id', $roleId)->delete();

        return true;
    }

    /**
     * @param  array<string, mixed>  $data  campos da role + permission_ids
     */
    public function createRole(array $data): Role
    {
        $permissionIds = (array) ($data['permission_ids'] ?? []);
        unset($data['permission_ids'], $data['is_sistema']);

        return DB::transaction(function () use ($data, $permissionIds) {
            $role = Role::create($data);
            $role->permissoes()->sync($this->idsValidos($permissionIds));

            return $role->load('permissoes');
        });
    }

    /**
     * "permission_ids" ausente mantém as permissões atuais.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRole(int $roleId, array $data): Role
    {
        $permissionIds = array_key_exists('permission_ids', $data) ? (array) $data['permission_ids'] : null;
        unset($data['permission_ids'], $data['is_sistema']);

        return DB::transaction(function () use ($roleId, $data, $permissionIds) {
            $role = Role::findOrFail($roleId);
            $role->fill($data)->saveOrFail();
            if ($permissionIds !== null) {
                $role->permissoes()->sync($this->idsValidos($permissionIds));
            }

            return $role->load('permissoes');
        });
    }

    /**
     * @throws \DomainException se a role for do sistema
     */
    public function deleteRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        if ($role->is_sistema) {
            throw new \DomainException('Não é possível excluir uma role do sistema');
        }

        $role->delete();
    }

    public function logAudit(int $usuarioId, string $acao, string $entidade, int $entidadeId, mixed $oldData, mixed $newData): void
    {
        LogPermissao::create([
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'dados_anteriores' => $oldData ?: null,
            'dados_novos' => $newData ?: null,
        ]);
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<int>
     */
    private function idsValidos(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}

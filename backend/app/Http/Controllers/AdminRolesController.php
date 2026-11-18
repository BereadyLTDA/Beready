<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\PermissionService;
use App\Support\ValidacaoCake;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Acesso restrito a admins pelo middleware `admin` (grupo /admin).
 */
class AdminRolesController extends Controller
{
    public function __construct(private PermissionService $permissionService) {}

    public function index(): JsonResponse
    {
        try {
            $roles = Role::with('permissoes')->where('is_ativo', true)->orderByDesc('nivel')->get();

            return $this->jsonSuccess($roles);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::index: '.$e->getMessage());

            return $this->jsonError('Erro ao listar roles', 500);
        }
    }

    public function add(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        $validacao = $this->validar($data, null);
        if ($validacao->temErros()) {
            return $this->jsonError($validacao->resumo(), 400, $validacao->erros());
        }

        try {
            $role = $this->permissionService->createRole($data);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::add: '.$e->getMessage());

            return $this->jsonError('Erro ao criar role', 500);
        }

        return $this->jsonSuccess($role, 'Role criada com sucesso');
    }

    public function edit(Request $request, string $id): JsonResponse
    {
        $data = $this->requestData($request);

        if (! Role::whereKey((int) $id)->exists()) {
            return $this->jsonError('Role não encontrada', 404);
        }

        $validacao = $this->validar($data, (int) $id);
        if ($validacao->temErros()) {
            return $this->jsonError($validacao->resumo(), 400, $validacao->erros());
        }

        try {
            $role = $this->permissionService->updateRole((int) $id, $data);
        } catch (ModelNotFoundException) {
            return $this->jsonError('Role não encontrada', 404);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::edit: '.$e->getMessage());

            return $this->jsonError('Erro ao atualizar role', 500);
        }

        return $this->jsonSuccess($role, 'Role atualizada com sucesso');
    }

    public function delete(string $id): JsonResponse
    {
        try {
            $this->permissionService->deleteRole((int) $id);
        } catch (ModelNotFoundException) {
            return $this->jsonError('Role não encontrada', 404);
        } catch (\DomainException $e) {
            // Role do sistema
            return $this->jsonError($e->getMessage(), 403);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::delete: '.$e->getMessage());

            return $this->jsonError('Erro ao excluir role', 500);
        }

        return $this->jsonSuccess(null, 'Role excluída com sucesso');
    }

    /**
     * Mesmas regras do RolesTable do CakePHP: nome obrigatório e único.
     *
     * @param  array<string, mixed>  $data
     */
    private function validar(array $data, ?int $roleId): ValidacaoCake
    {
        $validacao = new ValidacaoCake;
        $criando = $roleId === null;

        if (array_key_exists('nome', $data) || $criando) {
            $nome = $data['nome'] ?? null;
            if ($nome === null || $nome === '') {
                $validacao->adicionar('nome', '_empty', 'O nome é obrigatório');
            } elseif (Role::where('nome', $nome)->when($roleId, fn ($q) => $q->whereKeyNot($roleId))->exists()) {
                $validacao->adicionar('nome', 'unique', 'Já existe uma role com este nome');
            }
        }

        return $validacao;
    }
}

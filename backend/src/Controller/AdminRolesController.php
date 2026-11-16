<?php

namespace App\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Acesso restrito a admins pelo AdminMiddleware (escopo /admin).
 */
class AdminRolesController extends AppController
{
    private $rolesTable;

    public function initialize(): void
    {
        parent::initialize();
        $this->rolesTable = TableRegistry::getTableLocator()->get('Roles');
    }

    public function index()
    {
        try {
            $roles = $this->rolesTable->find()
                ->contain(['Permissoes'])
                ->where(['Roles.is_ativo' => true])
                ->orderBy(['Roles.nivel' => 'DESC'])
                ->toArray();

            return $this->jsonSuccess($roles);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::index: ' . $e->getMessage());
            return $this->jsonError('Erro ao listar roles', 500);
        }
    }

    public function add()
    {
        try {
            $result = $this->permissionService->createRole($this->getRequestData());
        } catch (\Exception $e) {
            Log::error('AdminRolesController::add: ' . $e->getMessage());
            return $this->jsonError('Erro ao criar role', 500);
        }

        if (!$result['success']) {
            return $this->validationError($result['errors']);
        }

        return $this->jsonSuccess($result['data'], 'Role criada com sucesso');
    }

    public function edit($id)
    {
        try {
            $result = $this->permissionService->updateRole((int)$id, $this->getRequestData());
        } catch (RecordNotFoundException $e) {
            return $this->jsonError('Role não encontrada', 404);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::edit: ' . $e->getMessage());
            return $this->jsonError('Erro ao atualizar role', 500);
        }

        if (!$result['success']) {
            return $this->validationError($result['errors']);
        }

        return $this->jsonSuccess($result['data'], 'Role atualizada com sucesso');
    }

    public function delete($id)
    {
        try {
            $result = $this->permissionService->deleteRole((int)$id);
        } catch (RecordNotFoundException $e) {
            return $this->jsonError('Role não encontrada', 404);
        } catch (\Exception $e) {
            Log::error('AdminRolesController::delete: ' . $e->getMessage());
            return $this->jsonError('Erro ao excluir role', 500);
        }

        if (!$result['success']) {
            return isset($result['error'])
                ? $this->jsonError($result['error'], 403)
                : $this->jsonError('Erro ao excluir role', 500);
        }

        return $this->jsonSuccess(null, 'Role excluída com sucesso');
    }

    private function validationError(array $errors)
    {
        $errorMessages = [];
        foreach ($errors as $field => $fieldErrors) {
            $errorMessages[] = $field . ': ' . implode(', ', $fieldErrors);
        }

        return $this->jsonError(implode('; ', $errorMessages), 400, $errors);
    }
}

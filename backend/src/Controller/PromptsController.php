<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\PermissionService;
use App\Services\PromptService;
use Cake\Http\ServerRequest;

class PromptsController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private PromptService $service,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /prompts
    public function index()
    {
        try {
            return $this->jsonSuccess($this->service->getPromptsByUsuario($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar prompts');
        }
    }

    // GET /prompts/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId') ?? $this->request->getQuery('usuarioId');

        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        try {
            if (!$this->canAccessUser((int)$userId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            return $this->jsonSuccess($this->service->getPromptsByUsuario((int)$userId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar prompts');
        }
    }

    // GET /prompts/view/{id}
    public function view($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');

        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }

        try {
            return $this->jsonSuccess($this->findOwnedPrompt((int)$promptId));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /prompts
    public function add()
    {
        try {
            $prompt = $this->service->createPrompt($this->currentUserId(), $this->getRequestData());
            return $this->jsonSuccess($prompt, 'Prompt criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar prompt');
        }
    }

    // PUT /prompts/edit/{id}
    public function edit($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id');

        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }

        try {
            $this->findOwnedPrompt((int)$promptId);
            $prompt = $this->service->updatePrompt((int)$promptId, $this->getRequestData());
            return $this->jsonSuccess($prompt, 'Prompt atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar prompt');
        }
    }

    // DELETE /prompts/delete/{id}
    public function delete($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id');

        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }

        try {
            $this->findOwnedPrompt((int)$promptId);
            $this->service->deletePrompt((int)$promptId);
            return $this->jsonSuccess(null, 'Prompt excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir prompt');
        }
    }

    /**
     * Prompt de outro usuário responde 404 (admin acessa todos).
     */
    private function findOwnedPrompt(int $id): array
    {
        $prompt = $this->service->getPromptById($id);
        if (!$this->canAccessUser((int)$prompt['usuario_id'])) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }

        return $prompt;
    }
}

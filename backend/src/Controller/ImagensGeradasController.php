<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contracts\PromptRepositoryInterface;
use App\Controller\Traits\PromptOwnershipTrait;
use App\Controller\Traits\ResourceErrorTrait;
use App\Services\ImagemService;
use App\Services\PermissionService;
use Cake\Http\ServerRequest;

class ImagensGeradasController extends AppController
{
    use PromptOwnershipTrait;
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private ImagemService $service,
        private PromptRepositoryInterface $promptRepository,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /imagens/prompt/{promptId}
    public function getByPrompt($promptId = null)
    {
        $promptId = $promptId ?? $this->request->getParam('promptId');

        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }

        try {
            if (!$this->canAccessPrompt((int)$promptId)) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            return $this->jsonSuccess($this->service->getImagensByPrompt((int)$promptId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar imagens');
        }
    }

    // GET /imagens/view/{id}
    public function view($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');

        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }

        try {
            return $this->jsonSuccess($this->findOwnedImagem((int)$imagemId));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /imagens
    public function add()
    {
        $data = $this->getRequestData();

        try {
            if (!empty($data['prompt_id']) && !$this->canAccessPrompt((int)$data['prompt_id'])) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $imagem = $this->service->createImagem($data);
            return $this->jsonSuccess($imagem, 'Imagem criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar imagem');
        }
    }

    // PUT /imagens/edit/{id}
    public function edit($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');

        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }

        $data = $this->getRequestData();

        try {
            $atual = $this->findOwnedImagem((int)$imagemId);
            if (!$this->canMoveToPrompt($atual, $data)) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $imagem = $this->service->updateImagem((int)$imagemId, $data);
            return $this->jsonSuccess($imagem, 'Imagem atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar imagem');
        }
    }

    // DELETE /imagens/delete/{id}
    public function delete($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');

        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }

        try {
            $this->findOwnedImagem((int)$imagemId);
            $this->service->deleteImagem((int)$imagemId);
            return $this->jsonSuccess(null, 'Imagem excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir imagem');
        }
    }

    private function findOwnedImagem(int $id): array
    {
        $imagem = $this->service->getImagemById($id);
        if (!$this->canAccessPrompt((int)$imagem['prompt_id'])) {
            throw new \RuntimeException('Imagem não encontrada', 404);
        }

        return $imagem;
    }
}

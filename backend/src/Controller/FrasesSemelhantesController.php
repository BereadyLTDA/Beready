<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contracts\PromptRepositoryInterface;
use App\Controller\Traits\PromptOwnershipTrait;
use App\Controller\Traits\ResourceErrorTrait;
use App\Services\FraseService;
use App\Services\GeracaoService;
use App\Services\PermissionService;
use Cake\Http\Response;
use Cake\Http\ServerRequest;

class FrasesSemelhantesController extends AppController
{
    use PromptOwnershipTrait;
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private FraseService $service,
        private PromptRepositoryInterface $promptRepository,
        private GeracaoService $geracaoService,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /frases/prompt/{promptId}
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

            return $this->jsonSuccess($this->service->getFrasesByPrompt((int)$promptId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar frases');
        }
    }

    // GET /frases/view/{id}
    public function view($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');

        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }

        try {
            return $this->jsonSuccess($this->findOwnedFrase((int)$fraseId));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /prompts/{id}/frases/gerar  { quantidade? } — gera frases semelhantes ao texto do prompt com IA
    public function gerar(string $id): Response
    {
        try {
            $prompt = $this->promptRepository->findById((int)$id);
            if (!$prompt || !$this->canAccessUser((int)$prompt['usuario_id'])) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $frases = $this->geracaoService->gerarFrases($prompt, $this->currentUserId(), $this->getRequestData());

            return $this->jsonSuccess($frases, 'Frases geradas com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao gerar frases');
        }
    }

    // POST /frases
    public function add()
    {
        $data = $this->getRequestData();

        try {
            if (!empty($data['prompt_id']) && !$this->canAccessPrompt((int)$data['prompt_id'])) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $frase = $this->service->createFrase($data);
            return $this->jsonSuccess($frase, 'Frase criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar frase');
        }
    }

    // PUT /frases/edit/{id}
    public function edit($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');

        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }

        $data = $this->getRequestData();

        try {
            $atual = $this->findOwnedFrase((int)$fraseId);
            if (!$this->canMoveToPrompt($atual, $data)) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $frase = $this->service->updateFrase((int)$fraseId, $data);
            return $this->jsonSuccess($frase, 'Frase atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar frase');
        }
    }

    // DELETE /frases/delete/{id}
    public function delete($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');

        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }

        try {
            $this->findOwnedFrase((int)$fraseId);
            $this->service->deleteFrase((int)$fraseId);
            return $this->jsonSuccess(null, 'Frase excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir frase');
        }
    }

    private function findOwnedFrase(int $id): array
    {
        $frase = $this->service->getFraseById($id);
        if (!$this->canAccessPrompt((int)$frase['prompt_id'])) {
            throw new \RuntimeException('Frase não encontrada', 404);
        }

        return $frase;
    }
}

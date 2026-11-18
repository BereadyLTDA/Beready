<?php

namespace App\Http\Controllers;

use App\Services\PromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptsController extends Controller
{
    public function __construct(private PromptService $service) {}

    // GET /prompts
    public function index(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->getPromptsByUsuario($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar prompts');
        }
    }

    // GET /prompts/usuario/{usuarioId}
    public function getByUsuario(string $usuarioId): JsonResponse
    {
        try {
            if (! $this->canAccessUser((int) $usuarioId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            return $this->jsonSuccess($this->service->getPromptsByUsuario((int) $usuarioId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar prompts');
        }
    }

    // GET /prompts/view/{id}
    public function view(string $id): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->findOwnedPrompt((int) $id));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /prompts — o dono é sempre o usuário autenticado (usuario_id do corpo é ignorado)
    public function add(Request $request): JsonResponse
    {
        try {
            $prompt = $this->service->createPrompt($this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($prompt, 'Prompt criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar prompt');
        }
    }

    // PUT /prompts/edit/{id}
    public function edit(Request $request, string $id): JsonResponse
    {
        try {
            $this->findOwnedPrompt((int) $id);
            $prompt = $this->service->updatePrompt((int) $id, $this->requestData($request));

            return $this->jsonSuccess($prompt, 'Prompt atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar prompt');
        }
    }

    // DELETE /prompts/delete/{id}
    public function delete(string $id): JsonResponse
    {
        try {
            $this->findOwnedPrompt((int) $id);
            $this->service->deletePrompt((int) $id);

            return $this->jsonSuccess(null, 'Prompt excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir prompt');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findOwnedPrompt(int $id): array
    {
        $prompt = $this->service->getPromptById($id);
        if (! $this->canAccessUser((int) $prompt['usuario_id'])) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }

        return $prompt;
    }
}

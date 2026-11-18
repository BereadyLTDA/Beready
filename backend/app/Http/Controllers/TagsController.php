<?php

namespace App\Http\Controllers;

use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    public function __construct(private TagService $service) {}

    // GET /tags — tags do usuário atual + tags de sistema
    public function index(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->getTagsVisibleToUsuario($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags');
        }
    }

    // GET /tags/usuario/{usuarioId}
    public function getByUsuario(string $usuarioId): JsonResponse
    {
        try {
            if (! $this->canAccessUser((int) $usuarioId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            return $this->jsonSuccess($this->service->getTagsByUsuario((int) $usuarioId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags');
        }
    }

    // GET /tags/view/{id} — tag de sistema é visível a todos
    public function view(string $id): JsonResponse
    {
        try {
            $tag = $this->service->getTagById((int) $id);
            if (empty($tag['tag_sistema']) && ! $this->canAccessUser((int) $tag['criado_por'])) {
                return $this->jsonError('Tag não encontrada', 404);
            }

            return $this->jsonSuccess($tag);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /tags
    public function add(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $tag = $this->service->createTag($this->currentUserId(), $data, $this->canSetTagSistema($data));

            return $this->jsonSuccess($tag, 'Tag criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar tag');
        }
    }

    // PUT /tags/edit/{id}
    public function edit(Request $request, string $id): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $this->findOwnedTag((int) $id);
            $tag = $this->service->updateTag((int) $id, $data, $this->canSetTagSistema($data));

            return $this->jsonSuccess($tag, 'Tag atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar tag');
        }
    }

    // DELETE /tags/delete/{id}
    public function delete(string $id): JsonResponse
    {
        try {
            $this->findOwnedTag((int) $id);
            $this->service->deleteTag((int) $id);

            return $this->jsonSuccess(null, 'Tag excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir tag');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findOwnedTag(int $id): array
    {
        $tag = $this->service->getTagById($id);
        if (! $this->canAccessUser((int) $tag['criado_por'])) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }

        return $tag;
    }

    /**
     * Só admin marca uma tag como de sistema (visível a todos).
     *
     * @param  array<string, mixed>  $data
     */
    private function canSetTagSistema(array $data): bool
    {
        return array_key_exists('tag_sistema', $data) && $this->isAdmin();
    }
}

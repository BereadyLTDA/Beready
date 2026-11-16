<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\PermissionService;
use App\Services\TagService;
use Cake\Http\ServerRequest;

class TagsController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private TagService $service,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /tags — tags do usuário atual + tags de sistema
    public function index()
    {
        try {
            $tags = $this->service->getTagsVisibleToUsuario($this->currentUserId());
            return $this->jsonSuccess($tags);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags');
        }
    }

    // GET /tags/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId');

        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        try {
            if (!$this->canAccessUser((int)$userId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            $tags = $this->service->getTagsByUsuario((int)$userId);
            return $this->jsonSuccess($tags);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags');
        }
    }

    // GET /tags/view/{id}
    public function view($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');

        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }

        try {
            $tag = $this->service->getTagById((int)$tagId);
            if (empty($tag['tag_sistema']) && !$this->canAccessUser((int)$tag['criado_por'])) {
                return $this->jsonError('Tag não encontrada', 404);
            }
            return $this->jsonSuccess($tag);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /tags
    public function add()
    {
        $data = $this->getRequestData();

        try {
            $tag = $this->service->createTag($this->currentUserId(), $data, $this->canSetTagSistema($data));
            return $this->jsonSuccess($tag, 'Tag criada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar tag');
        }
    }

    // PUT /tags/edit/{id}
    public function edit($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');

        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }

        $data = $this->getRequestData();

        try {
            $this->findOwnedTag((int)$tagId);
            $tag = $this->service->updateTag((int)$tagId, $data, $this->canSetTagSistema($data));
            return $this->jsonSuccess($tag, 'Tag atualizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar tag');
        }
    }

    // DELETE /tags/delete/{id}
    public function delete($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');

        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }

        try {
            $this->findOwnedTag((int)$tagId);
            $this->service->deleteTag((int)$tagId);
            return $this->jsonSuccess(null, 'Tag excluída com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir tag');
        }
    }

    /**
     * Alterar/excluir: só o criador ou admin (inclusive tags de sistema). Senão 404.
     */
    private function findOwnedTag(int $id): array
    {
        $tag = $this->service->getTagById($id);
        if (!$this->canAccessUser((int)$tag['criado_por'])) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }

        return $tag;
    }

    private function canSetTagSistema(array $data): bool
    {
        return array_key_exists('tag_sistema', $data) && $this->isAdmin();
    }
}

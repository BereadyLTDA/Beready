<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\FlashcardService;
use App\Services\FlashcardTagService;
use App\Services\PermissionService;
use App\Services\TagService;
use Cake\Http\ServerRequest;

/**
 * A associação pertence ao dono do flashcard; a tag precisa ser do usuário ou de sistema.
 */
class FlashcardTagsController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private FlashcardTagService $service,
        private FlashcardService $flashcardService,
        private TagService $tagService,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /flashcard-tags/flashcard/{flashcardId}
    public function getByFlashcard($flashcardId = null)
    {
        $flashcardId = $flashcardId ?? $this->request->getParam('flashcardId');

        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }

        try {
            $this->assertFlashcardAccess((int)$flashcardId);
            return $this->jsonSuccess($this->service->getTagsByFlashcard((int)$flashcardId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags do flashcard');
        }
    }

    // POST /flashcard-tags
    public function add()
    {
        $data = $this->getRequestData();

        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            return $this->jsonError('flashcard_id e tag_id são obrigatórios', 400);
        }

        try {
            $this->assertFlashcardAccess((int)$data['flashcard_id']);

            $tag = $this->tagService->getTagById((int)$data['tag_id']);
            if (empty($tag['tag_sistema']) && !$this->canAccessUser((int)$tag['criado_por'])) {
                return $this->jsonError('Tag não encontrada', 404);
            }

            $relation = $this->service->addTagToFlashcard((int)$data['flashcard_id'], (int)$data['tag_id']);
            return $this->jsonSuccess($relation, 'Tag adicionada ao flashcard com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao associar tag');
        }
    }

    // DELETE /flashcard-tags (com body)
    public function remove()
    {
        $data = $this->getRequestData();

        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            return $this->jsonError('flashcard_id e tag_id são obrigatórios', 400);
        }

        try {
            $this->assertFlashcardAccess((int)$data['flashcard_id']);

            if (!$this->service->removeTagFromFlashcard((int)$data['flashcard_id'], (int)$data['tag_id'])) {
                return $this->jsonError('Erro ao remover tag', 500);
            }

            return $this->jsonSuccess(null, 'Tag removida do flashcard com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao remover tag');
        }
    }

    /**
     * Flashcard de outro usuário responde 404 (admin acessa todos).
     */
    private function assertFlashcardAccess(int $flashcardId): void
    {
        $flashcard = $this->flashcardService->getFlashcardById($flashcardId);
        if (!$this->canAccessUser((int)$flashcard['usuario_id'])) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
    }
}

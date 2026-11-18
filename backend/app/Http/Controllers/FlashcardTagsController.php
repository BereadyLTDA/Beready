<?php

namespace App\Http\Controllers;

use App\Models\FlashcardTag;
use App\Services\FlashcardService;
use App\Services\TagService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A associação pertence ao dono do flashcard; a tag precisa ser do usuário ou de sistema.
 */
class FlashcardTagsController extends Controller
{
    public function __construct(
        private FlashcardService $flashcardService,
        private TagService $tagService,
    ) {}

    // GET /flashcard-tags/flashcard/{flashcardId}
    public function getByFlashcard(string $flashcardId): JsonResponse
    {
        try {
            $this->assertFlashcardAccess((int) $flashcardId);

            $relacoes = FlashcardTag::with('tag')->where('flashcard_id', (int) $flashcardId)->get();

            return $this->jsonSuccess($relacoes);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar tags do flashcard');
        }
    }

    // POST /flashcard-tags  { flashcard_id, tag_id }
    public function add(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            return $this->jsonError('flashcard_id e tag_id são obrigatórios', 400);
        }

        $flashcardId = (int) $data['flashcard_id'];
        $tagId = (int) $data['tag_id'];

        try {
            $this->assertFlashcardAccess($flashcardId);

            $tag = $this->tagService->getTagById($tagId);
            if (empty($tag['tag_sistema']) && ! $this->canAccessUser((int) $tag['criado_por'])) {
                return $this->jsonError('Tag não encontrada', 404);
            }

            if ($this->associacao($flashcardId, $tagId)->exists()) {
                throw new \RuntimeException('Tag já associada a este flashcard', 409);
            }

            $relacao = FlashcardTag::create(['flashcard_id' => $flashcardId, 'tag_id' => $tagId]);

            return $this->jsonSuccess($relacao, 'Tag adicionada ao flashcard com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao associar tag');
        }
    }

    // DELETE /flashcard-tags (com body { flashcard_id, tag_id })
    public function remove(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            return $this->jsonError('flashcard_id e tag_id são obrigatórios', 400);
        }

        $flashcardId = (int) $data['flashcard_id'];
        $tagId = (int) $data['tag_id'];

        try {
            $this->assertFlashcardAccess($flashcardId);

            $relacao = $this->associacao($flashcardId, $tagId)->first();
            if (! $relacao) {
                throw new \RuntimeException('Associação não encontrada', 404);
            }

            if (! $relacao->delete()) {
                return $this->jsonError('Erro ao remover tag', 500);
            }

            return $this->jsonSuccess(null, 'Tag removida do flashcard com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao remover tag');
        }
    }

    /**
     * @return Builder<FlashcardTag>
     */
    private function associacao(int $flashcardId, int $tagId)
    {
        return FlashcardTag::where('flashcard_id', $flashcardId)->where('tag_id', $tagId);
    }

    private function assertFlashcardAccess(int $flashcardId): void
    {
        $flashcard = $this->flashcardService->getFlashcardById($flashcardId);
        if (! $this->canAccessUser((int) $flashcard['usuario_id'])) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
    }
}

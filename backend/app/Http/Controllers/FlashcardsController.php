<?php

namespace App\Http\Controllers;

use App\Services\FlashcardService;
use App\Services\RepeticaoEspacadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashcardsController extends Controller
{
    public function __construct(private FlashcardService $flashcardService) {}

    // GET /flashcards
    public function index(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->flashcardService->getFlashcardsByUsuario($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar flashcards');
        }
    }

    // GET /flashcards/{id} e /flashcards/view/{id}
    public function view(string $id): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->findOwnedFlashcard((int) $id));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // GET /flashcards/{uuid}
    public function viewByUuid(string $uuid): JsonResponse
    {
        try {
            $flashcard = $this->flashcardService->getFlashcardByUuid($uuid);
            if (! $this->canAccessUser((int) $flashcard['usuario_id'])) {
                return $this->jsonError('Flashcard não encontrado', 404);
            }

            return $this->jsonSuccess($flashcard);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /flashcards
    public function add(Request $request): JsonResponse
    {
        try {
            $flashcard = $this->flashcardService->createFlashcard($this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($flashcard, 'Flashcard criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar flashcard');
        }
    }

    // PUT /flashcards/edit/{id}
    public function edit(Request $request, string $id): JsonResponse
    {
        try {
            $this->findOwnedFlashcard((int) $id);
            $flashcard = $this->flashcardService->updateFlashcard((int) $id, $this->requestData($request));

            return $this->jsonSuccess($flashcard, 'Flashcard atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar flashcard');
        }
    }

    // DELETE /flashcards/delete/{id}
    public function delete(string $id): JsonResponse
    {
        try {
            $this->findOwnedFlashcard((int) $id);
            $this->flashcardService->deleteFlashcard((int) $id);

            return $this->jsonSuccess(null, 'Flashcard excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir flashcard');
        }
    }

    // GET /flashcards/revisao[?limite=N] — flashcards do usuário com revisão vencida
    public function devidos(Request $request): JsonResponse
    {
        $limite = $request->query('limite');
        $limite = is_numeric($limite) && (int) $limite > 0 ? min((int) $limite, 500) : null;

        try {
            return $this->jsonSuccess($this->flashcardService->getDevidos($this->currentUserId(), $limite));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar flashcards para revisar');
        }
    }

    // POST /flashcards/{id}/revisao  { nota: 'errei' | 'bom' | 'facil' }
    public function revisao(Request $request, string $id): JsonResponse
    {
        $nota = $this->requestData($request)['nota'] ?? null;
        $notas = array_keys(RepeticaoEspacadaService::NOTAS);

        if (! is_string($nota) || ! in_array($nota, $notas, true)) {
            return $this->jsonError('Avaliação inválida', 422, [
                'nota' => ['inList' => 'Use um destes valores: '.implode(', ', $notas)],
            ]);
        }

        try {
            // Só o dono (nem admin): a revisão mexe no agendamento e no progresso de quem estuda
            $flashcard = $this->flashcardService->getFlashcardById((int) $id);
            if ((int) $flashcard['usuario_id'] !== $this->currentUserId()) {
                return $this->jsonError('Flashcard não encontrado', 404);
            }

            $flashcard = $this->flashcardService->revisar((int) $id, $this->currentUserId(), $nota);

            return $this->jsonSuccess($flashcard, 'Revisão registrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao registrar revisão');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findOwnedFlashcard(int $id): array
    {
        $flashcard = $this->flashcardService->getFlashcardById($id);
        if (! $this->canAccessUser((int) $flashcard['usuario_id'])) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }

        return $flashcard;
    }
}

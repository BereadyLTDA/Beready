<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\FlashcardService;
use App\Services\PermissionService;
use App\Services\RepeticaoEspacadaService;
use Cake\Http\ServerRequest;

class FlashcardsController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private FlashcardService $flashcardService,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /flashcards
    public function index()
    {
        try {
            $flashcards = $this->flashcardService->getFlashcardsByUsuario($this->currentUserId());
            return $this->jsonSuccess($flashcards);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar flashcards');
        }
    }

    // GET /flashcards/view/{id}
    public function view($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');

        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }

        try {
            return $this->jsonSuccess($this->findOwnedFlashcard((int)$flashcardId));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // GET /flashcards/{uuid}
    public function viewByUuid($uuid = null)
    {
        $flashcardUuid = $uuid ?? $this->request->getParam('uuid');

        if (!$flashcardUuid) {
            return $this->jsonError('UUID do flashcard não informado', 400);
        }

        try {
            $flashcard = $this->flashcardService->getFlashcardByUuid((string)$flashcardUuid);
            if (!$this->canAccessUser((int)$flashcard['usuario_id'])) {
                return $this->jsonError('Flashcard não encontrado', 404);
            }
            return $this->jsonSuccess($flashcard);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /flashcards
    public function add()
    {
        try {
            $flashcard = $this->flashcardService->createFlashcard($this->currentUserId(), $this->getRequestData());
            return $this->jsonSuccess($flashcard, 'Flashcard criado com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao criar flashcard');
        }
    }

    // PUT /flashcards/edit/{id}
    public function edit($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');

        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }

        try {
            $this->findOwnedFlashcard((int)$flashcardId);
            $flashcard = $this->flashcardService->updateFlashcard((int)$flashcardId, $this->getRequestData());
            return $this->jsonSuccess($flashcard, 'Flashcard atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar flashcard');
        }
    }

    // DELETE /flashcards/delete/{id}
    public function delete($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');

        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }

        try {
            $this->findOwnedFlashcard((int)$flashcardId);
            $this->flashcardService->deleteFlashcard((int)$flashcardId);
            return $this->jsonSuccess(null, 'Flashcard excluído com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao excluir flashcard');
        }
    }

    // GET /flashcards/revisao[?limite=N] — flashcards do usuário com revisão vencida
    public function devidos()
    {
        $limite = $this->request->getQuery('limite');
        $limite = is_numeric($limite) && (int)$limite > 0 ? min((int)$limite, 500) : null;

        try {
            return $this->jsonSuccess($this->flashcardService->getDevidos($this->currentUserId(), $limite));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar flashcards para revisar');
        }
    }

    // POST /flashcards/{id}/revisao  { nota: 'errei' | 'bom' | 'facil' }
    public function revisao($id = null)
    {
        $nota = $this->getRequestData()['nota'] ?? null;
        $notas = array_keys(RepeticaoEspacadaService::NOTAS);

        if (!is_string($nota) || !in_array($nota, $notas, true)) {
            return $this->jsonError('Avaliação inválida', 422, [
                'nota' => ['inList' => 'Use um destes valores: ' . implode(', ', $notas)],
            ]);
        }

        try {
            // Só o dono (nem admin): a revisão mexe no agendamento e no progresso de quem estuda
            $flashcard = $this->flashcardService->getFlashcardById((int)$id);
            if ((int)$flashcard['usuario_id'] !== $this->currentUserId()) {
                return $this->jsonError('Flashcard não encontrado', 404);
            }
            $flashcard = $this->flashcardService->revisar((int)$id, $this->currentUserId(), $nota);

            return $this->jsonSuccess($flashcard, 'Revisão registrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao registrar revisão');
        }
    }

    /**
     * Flashcard de outro usuário responde 404, como se não existisse (admin acessa todos).
     */
    private function findOwnedFlashcard(int $id): array
    {
        $flashcard = $this->flashcardService->getFlashcardById($id);
        if (!$this->canAccessUser((int)$flashcard['usuario_id'])) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }

        return $flashcard;
    }
}

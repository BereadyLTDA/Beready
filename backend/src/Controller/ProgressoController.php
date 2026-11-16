<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\PermissionService;
use App\Services\ProgressoService;
use Cake\Http\ServerRequest;

/**
 * Escritas sempre no usuário autenticado; usuario_id enviado no corpo é ignorado.
 */
class ProgressoController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private ProgressoService $service,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /progresso/usuario/{usuarioId}
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

            return $this->jsonSuccess($this->service->getResumo((int)$userId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar progresso');
        }
    }

    // POST /progresso
    public function save()
    {
        try {
            $progresso = $this->service->save($this->currentUserId(), $this->getRequestData());
            return $this->jsonSuccess($progresso, 'Progresso salvo com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar progresso');
        }
    }

    // POST /progresso/incrementar-flashcards
    public function incrementarFlashcards()
    {
        $data = $this->getRequestData();
        $quantidade = isset($data['quantidade']) ? (int)$data['quantidade'] : 1;

        try {
            $progresso = $this->service->incrementarFlashcards($this->currentUserId(), $quantidade);
            return $this->jsonSuccess($progresso, 'Progresso incrementado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao incrementar progresso');
        }
    }

    // POST /progresso/incrementar-tempo
    public function incrementarTempo()
    {
        $data = $this->getRequestData();
        $segundos = isset($data['segundos']) ? (int)$data['segundos'] : 0;

        try {
            $progresso = $this->service->incrementarTempo($this->currentUserId(), $segundos);
            return $this->jsonSuccess($progresso, 'Tempo de estudo atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar tempo de estudo');
        }
    }
}

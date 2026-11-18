<?php

namespace App\Http\Controllers;

use App\Services\ProgressoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escritas sempre no usuário autenticado; usuario_id enviado no corpo é ignorado.
 */
class ProgressoController extends Controller
{
    public function __construct(private ProgressoService $service) {}

    // GET /progresso/usuario/{usuarioId}
    public function getByUsuario(string $usuarioId): JsonResponse
    {
        try {
            if (! $this->canAccessUser((int) $usuarioId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            return $this->jsonSuccess($this->service->getResumo((int) $usuarioId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar progresso');
        }
    }

    // POST /progresso
    public function save(Request $request): JsonResponse
    {
        try {
            $progresso = $this->service->save($this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($progresso, 'Progresso salvo com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar progresso');
        }
    }

    // POST /progresso/incrementar-flashcards  { quantidade? }
    public function incrementarFlashcards(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        $quantidade = isset($data['quantidade']) ? (int) $data['quantidade'] : 1;

        try {
            $progresso = $this->service->incrementarFlashcards($this->currentUserId(), $quantidade);

            return $this->jsonSuccess($progresso, 'Progresso incrementado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao incrementar progresso');
        }
    }

    // POST /progresso/incrementar-tempo  { segundos }
    public function incrementarTempo(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        $segundos = isset($data['segundos']) ? (int) $data['segundos'] : 0;

        try {
            $progresso = $this->service->incrementarTempo($this->currentUserId(), $segundos);

            return $this->jsonSuccess($progresso, 'Tempo de estudo atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar tempo de estudo');
        }
    }
}

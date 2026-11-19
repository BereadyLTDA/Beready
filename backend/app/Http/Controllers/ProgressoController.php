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
    private const MAX_SEGUNDOS_POR_CHAMADA = 4 * 3600;

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
            // Contadores (flashcards, quizzes, sequência) são calculados pelo servidor: o cliente só
            // define o nível. Antes dava para mandar qualquer número e inflar o próprio progresso.
            $data = array_intersect_key($this->requestData($request), array_flip(['progresso_nivel']));
            $progresso = $this->service->save($this->currentUserId(), $data);

            return $this->jsonSuccess($progresso, 'Progresso salvo com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar progresso');
        }
    }

    // POST /progresso/incrementar-tempo  { segundos }
    public function incrementarTempo(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        $segundos = isset($data['segundos']) ? (int) $data['segundos'] : 0;
        // O tempo vem do cliente; um teto por chamada impede somar horas de uma vez
        if ($segundos > self::MAX_SEGUNDOS_POR_CHAMADA) {
            return $this->jsonError('Tempo de estudo inválido', 422);
        }

        try {
            $progresso = $this->service->incrementarTempo($this->currentUserId(), $segundos);

            return $this->jsonSuccess($progresso, 'Tempo de estudo atualizado com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar tempo de estudo');
        }
    }
}

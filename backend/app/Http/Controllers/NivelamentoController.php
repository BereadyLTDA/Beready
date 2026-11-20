<?php

namespace App\Http\Controllers;

use App\Services\NivelamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NivelamentoController extends Controller
{
    public function __construct(private NivelamentoService $service) {}

    // POST /nivelamento/iniciar — começa (ou continua) o teste e devolve a questão atual
    public function iniciar(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->iniciar($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao iniciar o teste de nivelamento');
        }
    }

    // POST /nivelamento/{id}/responder  { alternativa } — corrige no servidor e avança
    public function responder(Request $request, string $id): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->responder(
                $this->currentUserId(),
                (int) $id,
                $this->requestData($request)['alternativa'] ?? null,
            ));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao registrar a resposta');
        }
    }

    // GET /nivelamento/resultado — último resultado (data = null se nunca fez)
    public function resultado(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->resultado($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar o resultado do nivelamento');
        }
    }
}

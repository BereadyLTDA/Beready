<?php

namespace App\Http\Controllers;

use App\Services\ConquistaService;
use Illuminate\Http\JsonResponse;

class ConquistasController extends Controller
{
    public function __construct(private ConquistaService $service) {}

    // GET /conquistas — catálogo com o andamento; "nova" marca as que o aluno ainda não viu
    public function index(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->listar($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar conquistas');
        }
    }

    // POST /conquistas/vistas — o aluno viu os avisos das conquistas novas
    public function marcarVistas(): JsonResponse
    {
        try {
            $this->service->marcarVistas($this->currentUserId());

            return $this->jsonSuccess(null, 'Conquistas marcadas como vistas');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao atualizar conquistas');
        }
    }

    // GET /meta-diaria — meta de cards do dia, quantos já foram revisados hoje e a sequência
    public function metaDiaria(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->metaDiaria($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar a meta diária');
        }
    }
}

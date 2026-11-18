<?php

namespace App\Http\Controllers;

use App\Services\AssinaturaService;
use Illuminate\Http\JsonResponse;

class AssinaturasController extends Controller
{
    public function __construct(private AssinaturaService $assinaturaService) {}

    // GET /user/assinatura — sem assinatura ativa, o usuário vai para o Gratuito
    public function current(): JsonResponse
    {
        $assinatura = $this->assinaturaService->getAtivaOuGratuita($this->currentUserId());

        if (! $assinatura) {
            return $this->jsonError('Nenhuma assinatura ativa encontrada', 404);
        }

        return $this->jsonSuccess($assinatura);
    }
}

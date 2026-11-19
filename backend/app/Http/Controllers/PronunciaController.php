<?php

namespace App\Http\Controllers;

use App\Services\PronunciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PronunciaController extends Controller
{
    public function __construct(private PronunciaService $service) {}

    // GET /pronuncia/frases?limite=10 — frases em inglês para o exercício "Repita a frase"
    public function frases(Request $request): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->frasesParaPraticar(
                $this->currentUserId(),
                (int) $request->query('limite', '10'),
            ));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar frases');
        }
    }

    // POST /pronuncia/tentativas  { frase, reconhecido } — a nota é calculada aqui, não no cliente
    public function registrar(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $resultado = $this->service->registrar(
                $this->currentUserId(),
                (string) ($data['frase'] ?? ''),
                (string) ($data['reconhecido'] ?? ''),
            );

            return $this->jsonSuccess($resultado, 'Tentativa registrada', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao registrar a tentativa');
        }
    }

    // GET /pronuncia/resumo — total, média, melhor nota e as últimas tentativas
    public function resumo(): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->service->resumo($this->currentUserId()));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar o resumo de pronúncia');
        }
    }
}

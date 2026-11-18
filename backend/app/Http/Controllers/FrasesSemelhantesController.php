<?php

namespace App\Http\Controllers;

use App\Models\FraseSemelhante;
use App\Services\GeracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrasesSemelhantesController extends RecursoDoPromptController
{
    public function __construct(private GeracaoService $geracaoService) {}

    protected function model(): string
    {
        return FraseSemelhante::class;
    }

    protected function campoObrigatorio(): array
    {
        return ['frase_semelhante', 'Frase semelhante é obrigatória'];
    }

    protected function mensagens(): array
    {
        return [
            'nao_encontrado' => 'Frase não encontrada',
            'carregar' => 'Erro ao carregar frases',
            'criado' => 'Frase criada com sucesso',
            'criar' => 'Erro ao criar frase',
            'atualizado' => 'Frase atualizada com sucesso',
            'atualizar' => 'Erro ao atualizar frase',
            'excluido' => 'Frase excluída com sucesso',
            'excluir' => 'Erro ao excluir frase',
        ];
    }

    // As mais parecidas com o original primeiro
    protected function ordenacao(): array
    {
        return ['pontuacao_semelhante', 'desc'];
    }

    // POST /prompts/{id}/frases/gerar  { quantidade? } — gera frases semelhantes ao texto do prompt com IA
    public function gerar(Request $request, string $id): JsonResponse
    {
        try {
            $prompt = $this->promptDoUsuario((int) $id);
            if (! $prompt) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $frases = $this->geracaoService->gerarFrases($prompt, $this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($frases, 'Frases geradas com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao gerar frases');
        }
    }
}

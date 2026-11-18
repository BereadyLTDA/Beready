<?php

namespace App\Http\Controllers;

use App\Models\Traducao;
use App\Services\GeracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraducoesController extends RecursoDoPromptController
{
    public function __construct(private GeracaoService $geracaoService) {}

    protected function model(): string
    {
        return Traducao::class;
    }

    protected function campoObrigatorio(): array
    {
        return ['texto_traduzido', 'Texto traduzido é obrigatório'];
    }

    protected function mensagens(): array
    {
        return [
            'nao_encontrado' => 'Tradução não encontrada',
            'carregar' => 'Erro ao carregar traduções',
            'criado' => 'Tradução criada com sucesso',
            'criar' => 'Erro ao criar tradução',
            'atualizado' => 'Tradução atualizada com sucesso',
            'atualizar' => 'Erro ao atualizar tradução',
            'excluido' => 'Tradução excluída com sucesso',
            'excluir' => 'Erro ao excluir tradução',
        ];
    }

    protected function prepararDados(array $data): array
    {
        return self::prepararParaGravar($data);
    }

    /**
     * As traduções alternativas são gravadas como texto JSON, como na versão em CakePHP (que
     * codificava a lista antes de gravar na coluna jsonb). A API devolve esse texto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepararParaGravar(array $data): array
    {
        if (isset($data['traducoes_alternativas']) && is_array($data['traducoes_alternativas'])) {
            $data['traducoes_alternativas'] = json_encode($data['traducoes_alternativas']);
        }

        return $data;
    }

    // POST /prompts/{id}/traducoes/gerar  { idioma_destino } — traduz o texto do prompt com IA
    public function gerar(Request $request, string $id): JsonResponse
    {
        try {
            $prompt = $this->promptDoUsuario((int) $id);
            if (! $prompt) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $traducao = $this->geracaoService->gerarTraducao($prompt, $this->currentUserId(), $this->requestData($request));

            return $this->jsonSuccess($traducao, 'Tradução gerada com sucesso', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao gerar tradução');
        }
    }
}

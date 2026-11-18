<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Support\ValidacaoCake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Acesso restrito a admins pelo middleware `admin` (grupo /admin).
 */
class AdminPlanosController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Mostra todos os planos, inclusive inativos
            $planos = Plano::with('role')->orderBy('ordem')->get()->each(fn (Plano $p) => $this->castPrecos($p));

            return $this->jsonSuccess($planos);
        } catch (\Exception $e) {
            Log::error('AdminPlanosController::index: '.$e->getMessage());

            return $this->jsonError('Erro ao listar planos', 500);
        }
    }

    public function add(Request $request): JsonResponse
    {
        try {
            $data = $this->normalizeData($this->requestData($request));

            $validacao = $this->validar($data, null);
            if ($validacao->temErros()) {
                return $this->jsonError($validacao->resumo(), 400, $validacao->erros());
            }

            $plano = Plano::create($data);

            return $this->jsonSuccess($this->getSaved($plano->id), 'Plano criado com sucesso');
        } catch (\Exception $e) {
            Log::error('AdminPlanosController::add: '.$e->getMessage());

            return $this->jsonError('Erro ao criar plano', 500);
        }
    }

    public function edit(Request $request, string $id): JsonResponse
    {
        try {
            $plano = Plano::find((int) $id);
            if (! $plano) {
                return $this->jsonError('Plano não encontrado', 404);
            }

            $data = $this->normalizeData($this->requestData($request));

            $validacao = $this->validar($data, $plano->id);
            if ($validacao->temErros()) {
                return $this->jsonError($validacao->resumo(), 400, $validacao->erros());
            }

            $plano->fill($data)->saveOrFail();

            return $this->jsonSuccess($this->getSaved($plano->id), 'Plano atualizado com sucesso');
        } catch (\Exception $e) {
            Log::error('AdminPlanosController::edit: '.$e->getMessage());

            return $this->jsonError('Erro ao atualizar plano', 500);
        }
    }

    public function delete(string $id): JsonResponse
    {
        try {
            $plano = Plano::find((int) $id);
            if (! $plano) {
                return $this->jsonError('Plano não encontrado', 404);
            }

            if ($plano->delete()) {
                return $this->jsonSuccess(null, 'Plano excluído com sucesso');
            }

            return $this->jsonError('Erro ao excluir plano', 500);
        } catch (\Exception $e) {
            Log::error('AdminPlanosController::delete: '.$e->getMessage());

            return $this->jsonError('Erro ao excluir plano', 500);
        }
    }

    /**
     * O formulário do admin pode mandar recursos como texto separado por vírgula e limites como JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        if (isset($data['recursos']) && is_string($data['recursos'])) {
            $data['recursos'] = array_map('trim', explode(',', $data['recursos']));
        }

        if (isset($data['limites']) && is_string($data['limites'])) {
            $data['limites'] = json_decode($data['limites'], true);
        }

        return $data;
    }

    /**
     * Mesmas regras do PlanosTable do CakePHP.
     *
     * @param  array<string, mixed>  $data
     */
    private function validar(array $data, ?int $planoId): ValidacaoCake
    {
        $validacao = new ValidacaoCake;
        $criando = $planoId === null;

        if ($criando && ! array_key_exists('nome', $data)) {
            $validacao->adicionar('nome', '_required', 'This field is required');
        } elseif (array_key_exists('nome', $data)) {
            $nome = $data['nome'];
            if ($nome === null || $nome === '') {
                $validacao->adicionar('nome', '_empty', 'This field cannot be left empty');
            } elseif (! is_string($nome) || mb_strlen($nome) > 50) {
                $validacao->adicionar('nome', 'maxLength', 'The provided value must be at most `50` characters long');
            } elseif (Plano::where('nome', $nome)->when($planoId, fn ($q) => $q->whereKeyNot($planoId))->exists()) {
                $validacao->adicionar('nome', 'unique', 'Este nome já está em uso');
            }
        }

        foreach (['preco_mensal', 'preco_anual'] as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== '' && ! is_numeric($data[$campo])) {
                $validacao->adicionar($campo, 'decimal', 'The provided value must be decimal with any number of decimal places, including none');
            }
        }

        foreach (['role_id', 'dias_trial', 'ordem'] as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== '' && filter_var($data[$campo], FILTER_VALIDATE_INT) === false) {
                $validacao->adicionar($campo, 'integer', 'The provided value must be an integer');
            }
        }

        return $validacao;
    }

    private function getSaved(int $id): Plano
    {
        return $this->castPrecos(Plano::with('role')->findOrFail($id));
    }

    /**
     * O admin recebe os preços como número (a listagem pública mantém o decimal como texto).
     */
    private function castPrecos(Plano $plano): Plano
    {
        $plano->setAttribute('preco_mensal', (float) $plano->preco_mensal);
        $plano->setAttribute('preco_anual', (float) $plano->preco_anual);

        return $plano;
    }
}

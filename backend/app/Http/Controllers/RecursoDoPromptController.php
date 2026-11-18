<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de um recurso que pertence a um prompt (traduções, imagens, frases). Esses registros não têm
 * usuario_id: o dono é o dono do prompt pai, e mover um registro para o prompt de outro usuário é
 * proibido. Mesmas respostas e mensagens da versão em CakePHP (uma classe por recurso lá).
 */
abstract class RecursoDoPromptController extends Controller
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Campo obrigatório na criação => mensagem (além de prompt_id).
     *
     * @return array{0: string, 1: string}
     */
    abstract protected function campoObrigatorio(): array;

    /**
     * Mensagens: 'nao_encontrado', 'carregar', 'criado', 'criar', 'atualizado', 'atualizar', 'excluido', 'excluir'.
     *
     * @return array<string, string>
     */
    abstract protected function mensagens(): array;

    /**
     * Ajustes no registro antes de devolver.
     *
     * @return array<string, mixed>
     */
    protected function formatar(Model $registro): array
    {
        return $registro->toArray();
    }

    /**
     * Ajustes no corpo da requisição antes de gravar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepararDados(array $data): array
    {
        return $data;
    }

    /**
     * Ordem da listagem por prompt: [coluna, direção].
     *
     * @return array{0: string, 1: string}
     */
    protected function ordenacao(): array
    {
        return ['criado_em', 'desc'];
    }

    // GET /{recurso}/prompt/{promptId}
    public function getByPrompt(string $promptId): JsonResponse
    {
        try {
            if (! $this->canAccessPrompt((int) $promptId)) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $registros = $this->model()::where('prompt_id', (int) $promptId)->orderBy(...$this->ordenacao())->get();

            return $this->jsonSuccess($registros->map(fn (Model $r) => $this->formatar($r))->values());
        } catch (\Exception $e) {
            return $this->errorResponse($e, $this->mensagens()['carregar']);
        }
    }

    // GET /{recurso}/view/{id}
    public function view(string $id): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->formatar($this->findOwned((int) $id)));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // POST /{recurso}
    public function add(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            if (! empty($data['prompt_id']) && ! $this->canAccessPrompt((int) $data['prompt_id'])) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            if (empty($data['prompt_id'])) {
                throw new \InvalidArgumentException('ID do prompt é obrigatório');
            }

            [$campo, $mensagem] = $this->campoObrigatorio();
            if (empty($data[$campo])) {
                throw new \InvalidArgumentException($mensagem);
            }

            $registro = $this->model()::create($this->prepararDados($data));

            return $this->jsonSuccess($this->formatar($registro), $this->mensagens()['criado'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, $this->mensagens()['criar']);
        }
    }

    // PUT /{recurso}/edit/{id}
    public function edit(Request $request, string $id): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $registro = $this->findOwned((int) $id);
            if (! $this->canMoveToPrompt($registro, $data)) {
                return $this->jsonError('Prompt não encontrado', 404);
            }

            $registro->fill($this->prepararDados($data))->saveOrFail();

            return $this->jsonSuccess($this->formatar($registro), $this->mensagens()['atualizado']);
        } catch (\Exception $e) {
            return $this->errorResponse($e, $this->mensagens()['atualizar']);
        }
    }

    // DELETE /{recurso}/delete/{id}
    public function delete(string $id): JsonResponse
    {
        try {
            $this->findOwned((int) $id)->delete();

            return $this->jsonSuccess(null, $this->mensagens()['excluido']);
        } catch (\Exception $e) {
            return $this->errorResponse($e, $this->mensagens()['excluir']);
        }
    }

    /**
     * Registro que pertence a um prompt do usuário (ou o usuário é admin); senão, 404.
     */
    protected function findOwned(int $id): Model
    {
        $registro = $this->model()::find($id);
        if (! $registro || ! $this->canAccessPrompt((int) $registro->prompt_id)) {
            throw new \RuntimeException($this->mensagens()['nao_encontrado'], 404);
        }

        return $registro;
    }

    /**
     * Prompt existe e pertence ao usuário atual (ou o usuário é admin).
     */
    protected function canAccessPrompt(int $promptId): bool
    {
        $donoId = Prompt::whereKey($promptId)->value('usuario_id');

        return $donoId !== null && $this->canAccessUser((int) $donoId);
    }

    /**
     * Em uma edição, impede mover o registro para um prompt de outro usuário.
     *
     * @param  array<string, mixed>  $data
     */
    protected function canMoveToPrompt(Model $registro, array $data): bool
    {
        if (empty($data['prompt_id']) || (int) $data['prompt_id'] === (int) $registro->prompt_id) {
            return true;
        }

        return $this->canAccessPrompt((int) $data['prompt_id']);
    }

    /**
     * Prompt do usuário para a geração por IA, ou null (404) se não existir ou for de outro usuário.
     *
     * @return array<string, mixed>|null
     */
    protected function promptDoUsuario(int $promptId): ?array
    {
        $prompt = Prompt::find($promptId);

        return $prompt && $this->canAccessUser((int) $prompt->usuario_id) ? $prompt->toArray() : null;
    }
}

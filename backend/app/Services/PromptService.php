<?php

namespace App\Services;

use App\Models\Prompt;

class PromptService
{
    /**
     * @return array<string, mixed>
     */
    public function getPromptById(int $id): array
    {
        $prompt = Prompt::find($id);
        if (! $prompt) {
            throw new \RuntimeException('Prompt não encontrado', 404);
        }

        return $prompt->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPromptsByUsuario(int $usuarioId): array
    {
        return Prompt::where('usuario_id', $usuarioId)->orderByDesc('criado_em')->get()->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPrompt(int $usuarioId, array $data): array
    {
        if (empty($data['texto_original'])) {
            throw new \InvalidArgumentException('Texto original é obrigatório');
        }

        $data['usuario_id'] = $usuarioId;

        return Prompt::create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePrompt(int $id, array $data): array
    {
        unset($data['usuario_id']);

        $prompt = Prompt::findOrFail($id);
        $prompt->fill($data)->saveOrFail();

        return $prompt->toArray();
    }

    public function deletePrompt(int $id): bool
    {
        return (bool) Prompt::findOrFail($id)->delete();
    }
}

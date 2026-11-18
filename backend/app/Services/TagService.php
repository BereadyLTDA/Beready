<?php

namespace App\Services;

use App\Models\Tag;

class TagService
{
    /**
     * Tags do usuário + tags de sistema.
     *
     * @return list<array<string, mixed>>
     */
    public function getTagsVisibleToUsuario(int $usuarioId): array
    {
        return Tag::query()
            ->where(fn ($q) => $q->where('criado_por', $usuarioId)->orWhere('tag_sistema', true))
            ->orderBy('nome')
            ->get()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTagById(int $id): array
    {
        $tag = Tag::find($id);
        if (! $tag) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }

        return $tag->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTagsByUsuario(int $usuarioId): array
    {
        return Tag::where('criado_por', $usuarioId)->orderBy('nome')->get()->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTag(int $usuarioId, array $data, bool $podeTagSistema = false): array
    {
        if (empty($data['nome'])) {
            throw new \InvalidArgumentException('Nome da tag é obrigatório');
        }

        $data['criado_por'] = $usuarioId;
        if (! $podeTagSistema) {
            unset($data['tag_sistema']);
        }

        if ($this->nomeEmUso((string) $data['nome'], $usuarioId)) {
            throw new \RuntimeException('Tag já existe', 409);
        }

        $tag = Tag::create($data);

        return $tag->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateTag(int $id, array $data, bool $podeTagSistema = false): array
    {
        $tag = Tag::find($id);
        if (! $tag) {
            throw new \RuntimeException('Tag não encontrada', 404);
        }

        unset($data['criado_por']);
        if (! $podeTagSistema) {
            unset($data['tag_sistema']);
        }

        // Novo nome não pode repetir o de outra tag do mesmo dono
        if (isset($data['nome']) && $data['nome'] !== $tag->nome && $this->nomeEmUso((string) $data['nome'], $tag->criado_por)) {
            throw new \RuntimeException('Tag já existe', 409);
        }

        $tag->fill($data)->saveOrFail();

        return $tag->toArray();
    }

    public function deleteTag(int $id): bool
    {
        return (bool) Tag::findOrFail($id)->delete();
    }

    /**
     * Nome único por dono; tags sem dono (criado_por nulo) também não se repetem entre si.
     */
    private function nomeEmUso(string $nome, ?int $criadoPor): bool
    {
        return Tag::where('nome', $nome)
            ->when($criadoPor === null, fn ($q) => $q->whereNull('criado_por'), fn ($q) => $q->where('criado_por', $criadoPor))
            ->exists();
    }
}

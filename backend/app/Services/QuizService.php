<?php

namespace App\Services;

use App\Models\Quiz;

class QuizService
{
    /**
     * Campos das listagens (sem o uuid, como no CakePHP).
     */
    private const CAMPOS_LISTAGEM = [
        'id', 'usuario_id', 'titulo', 'descricao', 'tipo_criacao', 'nivel_dificuldade',
        'total_questoes', 'tempo_limite', 'publico', 'criado_em', 'atualizado_em',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function getQuizzesByUsuario(int $usuarioId): array
    {
        return Quiz::query()
            ->select(self::CAMPOS_LISTAGEM)
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('criado_em')
            ->get()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuizById(int $id): array
    {
        $quiz = Quiz::find($id);
        if (! $quiz) {
            throw new \RuntimeException('Quiz não encontrado', 404);
        }

        return $quiz->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createQuiz(int $usuarioId, array $data): array
    {
        if (empty($data['titulo'])) {
            throw new \InvalidArgumentException('Título é obrigatório');
        }

        $data['usuario_id'] = $usuarioId;
        $data['tipo_criacao'] = $data['tipo_criacao'] ?? 'manual';
        $data['nivel_dificuldade'] = $data['nivel_dificuldade'] ?? 'iniciante';
        // total_questoes é a contagem das questões, mantida pelo QuizQuestaoService
        $data['total_questoes'] = 0;
        $data['publico'] = ! empty($data['publico']);
        unset($data['questoes']);

        return $this->create($data);
    }

    /**
     * Grava o quiz como veio (usado também pelo gerador a partir dos flashcards).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return Quiz::create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateQuiz(int $id, array $data): array
    {
        unset($data['usuario_id'], $data['total_questoes'], $data['questoes']);

        $quiz = Quiz::findOrFail($id);
        $quiz->fill($data)->saveOrFail();

        return $quiz->toArray();
    }

    public function deleteQuiz(int $id): bool
    {
        return (bool) Quiz::findOrFail($id)->delete();
    }
}

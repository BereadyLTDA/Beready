<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\RespostaUsuario;
use Illuminate\Support\Facades\DB;

class FlashcardService
{
    /**
     * Campos das listagens (sem o uuid, como no CakePHP).
     */
    private const CAMPOS_LISTAGEM = [
        'id', 'usuario_id', 'frente', 'verso', 'nivel_dificuldade', 'criado_em', 'atualizado_em',
        'repeticoes', 'intervalo_dias', 'fator_ease', 'proxima_revisao', 'ultima_revisao',
    ];

    public function __construct(
        private RepeticaoEspacadaService $repeticaoEspacada,
        private ProgressoService $progresso,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function getFlashcardsByUsuario(int $usuarioId): array
    {
        return Flashcard::query()
            ->select(self::CAMPOS_LISTAGEM)
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('criado_em')
            ->get()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlashcardById(int $id): array
    {
        $flashcard = Flashcard::find($id);
        if (! $flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }

        return $flashcard->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlashcardByUuid(string $uuid): array
    {
        $flashcard = Flashcard::where('uuid', $uuid)->first();
        if (! $flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }

        return $flashcard->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createFlashcard(int $usuarioId, array $data): array
    {
        if (empty($data['frente'])) {
            throw new \InvalidArgumentException('A pergunta (frente) é obrigatória');
        }

        if (empty($data['verso'])) {
            throw new \InvalidArgumentException('A resposta (verso) é obrigatória');
        }

        $data['usuario_id'] = $usuarioId;

        $flashcard = new Flashcard($data);
        // Card novo já fica devido (relógio da aplicação, coerente com a listagem dos devidos)
        $flashcard->proxima_revisao = now();
        $flashcard->saveOrFail();

        // Recarrega para trazer os padrões do banco (uuid, repeticoes...)
        return $flashcard->fresh()->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateFlashcard(int $id, array $data): array
    {
        unset($data['usuario_id']);

        $flashcard = Flashcard::findOrFail($id);
        $flashcard->fill($data)->saveOrFail();

        return $flashcard->toArray();
    }

    public function deleteFlashcard(int $id): bool
    {
        return (bool) Flashcard::findOrFail($id)->delete();
    }

    /**
     * Fila do "Revisar agora": total de cards vencidos e os primeiros $limite deles.
     *
     * @return array{total: int, flashcards: list<array<string, mixed>>}
     */
    public function getDevidos(int $usuarioId, ?int $limite = null): array
    {
        $agora = now();
        $devidos = Flashcard::query()
            ->where('usuario_id', $usuarioId)
            ->where('proxima_revisao', '<=', $agora);

        return [
            'total' => (clone $devidos)->count(),
            'flashcards' => $devidos
                ->select(self::CAMPOS_LISTAGEM)
                ->orderBy('proxima_revisao')
                ->orderBy('id')
                ->when($limite !== null, fn ($q) => $q->limit($limite))
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Registra a avaliação do estudo: reagenda (SM-2), grava a resposta e soma o progresso.
     *
     * @return array<string, mixed>
     */
    public function revisar(int $flashcardId, int $usuarioId, string $nota): array
    {
        return DB::transaction(function () use ($flashcardId, $usuarioId, $nota) {
            $flashcard = Flashcard::findOrFail($flashcardId);
            $agendamento = $this->repeticaoEspacada->calcular($flashcard->toArray(), $nota, now());

            $flashcard->forceFill([
                'repeticoes' => $agendamento['repeticoes'],
                'intervalo_dias' => $agendamento['intervalo_dias'],
                'fator_ease' => round($agendamento['fator_ease'], 2),
                'ultima_revisao' => $agendamento['ultima_revisao'],
                'proxima_revisao' => $agendamento['proxima_revisao'],
            ])->saveOrFail();

            RespostaUsuario::create([
                'usuario_id' => $usuarioId,
                'tipo' => 'flashcard',
                'referencia_id' => $flashcardId,
                'correto' => $nota !== 'errei',
            ]);

            $this->progresso->incrementarFlashcards($usuarioId, 1);

            return $flashcard->toArray();
        });
    }
}

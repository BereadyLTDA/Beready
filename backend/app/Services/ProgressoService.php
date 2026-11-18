<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\ProgressoUsuario;
use App\Models\Quiz;
use App\Models\RespostaUsuario;
use Illuminate\Support\Facades\DB;

class ProgressoService
{
    /**
     * Progresso salvo + taxa de acerto e progresso geral calculados.
     *
     * @return array<string, mixed>
     */
    public function getResumo(int $usuarioId): array
    {
        $progresso = $this->find($usuarioId);
        $data = $progresso
            ? $progresso->toArray()
            : [
                'usuario_id' => $usuarioId,
                'vocabulario_aprendido' => 0,
                'flashcards_concluidos' => 0,
                'quizes_concluidos' => 0,
                'tempo_total_estudo' => 0,
                'sequencia_atual' => 0,
                'maior_sequencia' => 0,
                'ultima_atividade' => null,
                'progresso_nivel' => null,
            ];

        $totalRespostas = RespostaUsuario::where('usuario_id', $usuarioId)->count();
        $respostasCorretas = RespostaUsuario::where('usuario_id', $usuarioId)->where('correto', true)->count();

        $data['taxa_acerto'] = $totalRespostas > 0
            ? (int) round(($respostasCorretas / $totalRespostas) * 100)
            : 0;

        $totalFlashcards = Flashcard::where('usuario_id', $usuarioId)->count();
        $totalQuizes = Quiz::where('usuario_id', $usuarioId)->count();

        $percFlashcards = $totalFlashcards > 0 ? ($data['flashcards_concluidos'] / $totalFlashcards) : 0;
        $percQuizes = $totalQuizes > 0 ? ($data['quizes_concluidos'] / $totalQuizes) : 0;

        $data['progresso_geral'] = min(100, (int) round((($percFlashcards + $percQuizes) / 2) * 100));

        return $data;
    }

    /**
     * Cria ou atualiza o progresso do usuário.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(int $usuarioId, array $data): array
    {
        $data['usuario_id'] = $usuarioId;

        $progresso = $this->find($usuarioId) ?? new ProgressoUsuario;
        $progresso->fill($data)->saveOrFail();

        return $progresso->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function incrementarFlashcards(int $usuarioId, int $quantidade): array
    {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Dados inválidos para incrementar progresso');
        }

        $this->ensureExists($usuarioId);
        ProgressoUsuario::where('usuario_id', $usuarioId)
            ->update(['flashcards_concluidos' => DB::raw('flashcards_concluidos + '.$quantidade)]);
        $this->atualizarSequencia($usuarioId);

        return $this->find($usuarioId)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function incrementarQuizes(int $usuarioId, int $quantidade): array
    {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Dados inválidos para incrementar progresso');
        }

        $this->ensureExists($usuarioId);
        ProgressoUsuario::where('usuario_id', $usuarioId)
            ->update(['quizes_concluidos' => DB::raw('COALESCE(quizes_concluidos, 0) + '.$quantidade)]);
        $this->atualizarSequencia($usuarioId);

        return $this->find($usuarioId)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function incrementarTempo(int $usuarioId, int $segundos): array
    {
        if ($segundos <= 0) {
            throw new \InvalidArgumentException('Dados inválidos para incrementar tempo de estudo');
        }

        $this->ensureExists($usuarioId);
        ProgressoUsuario::where('usuario_id', $usuarioId)
            ->update(['tempo_total_estudo' => DB::raw('tempo_total_estudo + '.$segundos)]);

        return $this->find($usuarioId)->toArray();
    }

    private function find(int $usuarioId): ?ProgressoUsuario
    {
        return ProgressoUsuario::where('usuario_id', $usuarioId)->first();
    }

    private function ensureExists(int $usuarioId): void
    {
        if (! $this->find($usuarioId)) {
            ProgressoUsuario::create(['usuario_id' => $usuarioId]);
        }
    }

    /**
     * Dias seguidos de estudo: soma 1 se a última atividade foi ontem, recomeça se foi antes,
     * e não muda se já houve atividade hoje.
     */
    private function atualizarSequencia(int $usuarioId): void
    {
        $progresso = $this->find($usuarioId);
        if (! $progresso) {
            return;
        }

        $hoje = now()->startOfDay();
        $ultimaAtividade = $progresso->ultima_atividade?->copy()->startOfDay();

        if ($ultimaAtividade === null) {
            $novaSequencia = 1;
        } else {
            $diffDays = (int) abs($hoje->diffInDays($ultimaAtividade));
            if ($diffDays === 0) {
                return;
            }
            $novaSequencia = $diffDays === 1 ? ($progresso->sequencia_atual ?? 0) + 1 : 1;
        }

        ProgressoUsuario::where('usuario_id', $usuarioId)->update([
            'sequencia_atual' => $novaSequencia,
            'maior_sequencia' => max($progresso->maior_sequencia ?? 0, $novaSequencia),
            'ultima_atividade' => $hoje,
        ]);
    }
}

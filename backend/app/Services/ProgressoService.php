<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\ProgressoUsuario;
use App\Models\PronunciaTentativa;
use App\Models\Quiz;
use App\Models\RespostaUsuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProgressoService
{
    public const DIAS_HISTORICO = 30;

    public const SEMANAS_HISTORICO = 12;

    /** Intervalo (dias) a partir do qual o card conta como dominado */
    public const DIAS_DOMINADO = 21;

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

    /**
     * Séries para os gráficos de progresso, agrupadas no fuso da aplicação.
     *
     * @return array{
     *     revisoes_por_dia: list<array{dia: string, total: int}>,
     *     acertos_por_semana: list<array{semana: string, total: int, corretas: int, taxa: int}>,
     *     pronuncia_por_semana: list<array{semana: string, tentativas: int, media: int}>,
     *     distribuicao_cards: array{novos: int, aprendendo: int, dominados: int}
     * }
     */
    public function historico(int $usuarioId): array
    {
        $hoje = Carbon::today();
        $inicioDias = $hoje->copy()->subDays(self::DIAS_HISTORICO - 1);
        $inicioSemanas = $hoje->copy()->startOfWeek()->subWeeks(self::SEMANAS_HISTORICO - 1);

        // Agrupa em PHP (no fuso do app), não no SQL: o banco guarda sem fuso
        $respostas = RespostaUsuario::where('usuario_id', $usuarioId)
            ->where('criado_em', '>=', $inicioSemanas)
            ->get(['tipo', 'correto', 'criado_em']);

        $porDia = [];
        for ($d = $inicioDias->copy(); $d->lte($hoje); $d->addDay()) {
            $porDia[$d->toDateString()] = 0;
        }
        $porSemana = [];
        for ($s = $inicioSemanas->copy(); $s->lte($hoje); $s->addWeek()) {
            $porSemana[$s->toDateString()] = ['total' => 0, 'corretas' => 0];
        }

        foreach ($respostas as $r) {
            $quando = Carbon::parse($r->getAttribute('criado_em'));
            $dia = $quando->toDateString();
            if ($r->getAttribute('tipo') === 'flashcard' && isset($porDia[$dia])) {
                $porDia[$dia]++;
            }
            $semana = $quando->copy()->startOfWeek()->toDateString();
            if (isset($porSemana[$semana])) {
                $porSemana[$semana]['total']++;
                $porSemana[$semana]['corretas'] += $r->getAttribute('correto') ? 1 : 0;
            }
        }

        $pronuncia = array_fill_keys(array_keys($porSemana), []);
        $tentativas = PronunciaTentativa::where('usuario_id', $usuarioId)
            ->where('criado_em', '>=', $inicioSemanas)
            ->get(['nota', 'criado_em']);
        foreach ($tentativas as $t) {
            $semana = Carbon::parse($t->getAttribute('criado_em'))->startOfWeek()->toDateString();
            if (isset($pronuncia[$semana])) {
                $pronuncia[$semana][] = (int) $t->getAttribute('nota');
            }
        }

        // Pelo intervalo da repetição espaçada: nunca revisado, aprendendo, ou com intervalo longo
        $cards = Flashcard::where('usuario_id', $usuarioId);

        return [
            'revisoes_por_dia' => array_map(fn ($dia, $total) => ['dia' => $dia, 'total' => $total], array_keys($porDia), $porDia),
            'acertos_por_semana' => array_map(fn ($semana, $v) => [
                'semana' => $semana,
                'total' => $v['total'],
                'corretas' => $v['corretas'],
                'taxa' => $v['total'] > 0 ? (int) round($v['corretas'] / $v['total'] * 100) : 0,
            ], array_keys($porSemana), $porSemana),
            'pronuncia_por_semana' => array_map(fn ($semana, $notas) => [
                'semana' => $semana,
                'tentativas' => count($notas),
                'media' => $notas ? (int) round(array_sum($notas) / count($notas)) : 0,
            ], array_keys($pronuncia), $pronuncia),
            'distribuicao_cards' => [
                'novos' => (clone $cards)->where(fn ($q) => $q->whereNull('repeticoes')->orWhere('repeticoes', 0))->count(),
                'aprendendo' => (clone $cards)->where('repeticoes', '>', 0)->where(fn ($q) => $q->whereNull('intervalo_dias')->orWhere('intervalo_dias', '<', self::DIAS_DOMINADO))->count(),
                'dominados' => (clone $cards)->where('repeticoes', '>', 0)->where('intervalo_dias', '>=', self::DIAS_DOMINADO)->count(),
            ],
        ];
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

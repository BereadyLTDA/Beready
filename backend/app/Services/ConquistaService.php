<?php

namespace App\Services;

use App\Models\ConquistaUsuario;
use App\Models\Flashcard;
use App\Models\FraseSemelhante;
use App\Models\PreferenciaUsuario;
use App\Models\ProgressoUsuario;
use App\Models\Prompt;
use App\Models\PronunciaTentativa;
use App\Models\RespostaUsuario;
use Illuminate\Support\Carbon;

/**
 * Conquistas e meta diária. As regras são todas conferidas no servidor, a partir do que o próprio
 * backend registrou: o cliente não consegue se dar uma conquista.
 */
class ConquistaService
{
    public const METAS_PERMITIDAS = [5, 10, 20, 30];

    public const META_PADRAO = 10;

    /**
     * Ordem de exibição. "quiz_gabaritado" é dada no evento (QuizTentativaService); as outras são
     * calculadas por métrica em verificar().
     */
    public const CATALOGO = [
        'primeiro_card' => ['icone' => 'document', 'metrica' => 'flashcards', 'alvo' => 1],
        'cards_50' => ['icone' => 'refresh', 'metrica' => 'revisoes', 'alvo' => 50],
        'cards_200' => ['icone' => 'star', 'metrica' => 'revisoes', 'alvo' => 200],
        'sequencia_7' => ['icone' => 'clock', 'metrica' => 'sequencia', 'alvo' => 7],
        'sequencia_30' => ['icone' => 'chart-bar', 'metrica' => 'sequencia', 'alvo' => 30],
        'quiz_gabaritado' => ['icone' => 'check-circle', 'metrica' => null, 'alvo' => 1],
        'frases_ia_10' => ['icone' => 'chat', 'metrica' => 'frases', 'alvo' => 10],
        'pronuncia_90' => ['icone' => 'microphone', 'metrica' => 'pronuncia', 'alvo' => 90],
    ];

    /**
     * Confere as métricas e grava as conquistas que o aluno passou a merecer.
     *
     * @return list<string> códigos concedidos agora
     */
    public function verificar(int $usuarioId): array
    {
        $metricas = $this->metricas($usuarioId);
        $novas = [];
        foreach (self::CATALOGO as $codigo => $regra) {
            if ($regra['metrica'] !== null && $metricas[$regra['metrica']] >= $regra['alvo'] && $this->conceder($usuarioId, $codigo)) {
                $novas[] = $codigo;
            }
        }

        return $novas;
    }

    /**
     * Concede uma conquista (idempotente). Devolve true só se ela é nova.
     */
    public function conceder(int $usuarioId, string $codigo): bool
    {
        if (! isset(self::CATALOGO[$codigo])) {
            throw new \InvalidArgumentException("Conquista desconhecida: {$codigo}");
        }

        return ConquistaUsuario::query()->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'codigo' => $codigo,
            'conquistada_em' => now(),
            'vista' => false,
        ]) > 0;
    }

    /**
     * Catálogo inteiro com o andamento do aluno (as conquistas são verificadas antes).
     *
     * @return list<array{codigo: string, icone: string, alvo: int, atual: int, conquistada: bool, conquistada_em: string|null, nova: bool}>
     */
    public function listar(int $usuarioId): array
    {
        $this->verificar($usuarioId);
        $metricas = $this->metricas($usuarioId);
        $obtidas = ConquistaUsuario::where('usuario_id', $usuarioId)->get()->keyBy('codigo');

        $lista = [];
        foreach (self::CATALOGO as $codigo => $regra) {
            $obtida = $obtidas->get($codigo);
            $atual = $regra['metrica'] !== null ? $metricas[$regra['metrica']] : ($obtida ? 1 : 0);
            $lista[] = [
                'codigo' => $codigo,
                'icone' => $regra['icone'],
                'alvo' => $regra['alvo'],
                'atual' => min($atual, $regra['alvo']),
                'conquistada' => $obtida !== null,
                'conquistada_em' => $obtida?->getAttribute('conquistada_em')?->toAtomString(),
                'nova' => $obtida !== null && ! $obtida->getAttribute('vista'),
            ];
        }

        return $lista;
    }

    /**
     * O aluno viu os avisos: as conquistas deixam de ser "novas".
     */
    public function marcarVistas(int $usuarioId): void
    {
        ConquistaUsuario::where('usuario_id', $usuarioId)->where('vista', false)->update(['vista' => true]);
    }

    /**
     * @return array{meta: int, feitos_hoje: int, sequencia_atual: int}
     */
    public function metaDiaria(int $usuarioId): array
    {
        $meta = (int) (PreferenciaUsuario::where('usuario_id', $usuarioId)->value('meta_diaria_cards') ?? self::META_PADRAO);
        $feitos = RespostaUsuario::where('usuario_id', $usuarioId)
            ->where('tipo', 'flashcard')
            ->where('criado_em', '>=', Carbon::today())
            ->count();

        return [
            'meta' => $meta > 0 ? $meta : self::META_PADRAO,
            'feitos_hoje' => $feitos,
            'sequencia_atual' => (int) ProgressoUsuario::where('usuario_id', $usuarioId)->value('sequencia_atual'),
        ];
    }

    /**
     * @return array{flashcards: int, revisoes: int, sequencia: int, frases: int, pronuncia: int}
     */
    private function metricas(int $usuarioId): array
    {
        $progresso = ProgressoUsuario::where('usuario_id', $usuarioId)->first();

        return [
            'flashcards' => Flashcard::where('usuario_id', $usuarioId)->count(),
            'revisoes' => RespostaUsuario::where('usuario_id', $usuarioId)->where('tipo', 'flashcard')->count(),
            'sequencia' => (int) max($progresso?->getAttribute('maior_sequencia') ?? 0, $progresso?->getAttribute('sequencia_atual') ?? 0),
            'frases' => FraseSemelhante::whereIn('prompt_id', Prompt::where('usuario_id', $usuarioId)->select('id'))->count(),
            'pronuncia' => (int) PronunciaTentativa::where('usuario_id', $usuarioId)->max('nota'),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ConquistaUsuario;
use App\Models\Flashcard;
use App\Models\FraseSemelhante;
use App\Models\Nivelamento;
use App\Models\ProgressoUsuario;
use App\Models\Prompt;
use App\Models\PronunciaTentativa;
use App\Models\RespostaUsuario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Métricas de uso dos participantes da validação com usuários (item 6 do roadmap do TCC),
 * anonimizadas: cada participante vira P01, P02... na ordem da lista de e-mails recebida, e
 * nenhum dado pessoal (nome, e-mail, telefone, foto, texto dos cards) sai no relatório.
 *
 * Só entra quem está na lista: a exportação é restrita a quem assinou o termo de consentimento.
 */
class RelatorioEstudoService
{
    public const COLUNAS = [
        'participante',
        'nivel_ingles_atual',
        'nivelamento_inicial',
        'nivelamento_final',
        'dias_ativos',
        'flashcards_criados',
        'revisoes_flashcard',
        'acerto_flashcard_pct',
        'respostas_quiz',
        'acerto_quiz_pct',
        'acerto_primeira_metade_pct',
        'acerto_segunda_metade_pct',
        'prompts_criados',
        'frases_semelhantes',
        'pronuncia_tentativas',
        'pronuncia_nota_media',
        'maior_sequencia',
        'conquistas_no_periodo',
    ];

    /**
     * nao_encontrados traz as posições (1, 2...) da lista que não correspondem a nenhum usuário.
     *
     * @param  list<string>  $emails  participantes, na ordem que define o código (P01, P02...)
     * @return array{linhas: list<array<string, int|float|string|null>>, nao_encontrados: list<int>}
     */
    public function gerar(array $emails, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $inicio = $de->startOfDay();
        $fim = $ate->endOfDay();
        // Divide o período ao meio para comparar o acerto do começo com o do fim do estudo
        $meio = $inicio->addSeconds(intdiv($fim->getTimestamp() - $inicio->getTimestamp(), 2));

        $linhas = [];
        $naoEncontrados = [];
        $digitos = max(2, strlen((string) count($emails)));

        foreach ($emails as $i => $email) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();
            if (! $user) {
                $naoEncontrados[] = $i + 1;

                continue;
            }

            $codigo = 'P'.str_pad((string) ($i + 1), $digitos, '0', STR_PAD_LEFT);
            $linhas[] = $this->linha($codigo, $user, $inicio, $fim, $meio);
        }

        return ['linhas' => $linhas, 'nao_encontrados' => $naoEncontrados];
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function linha(string $codigo, User $user, CarbonImmutable $inicio, CarbonImmutable $fim, CarbonImmutable $meio): array
    {
        $id = (int) $user->id;
        $periodo = [$inicio, $fim];

        $respostas = RespostaUsuario::where('usuario_id', $id)->whereBetween('criado_em', $periodo)->get(['tipo', 'correto', 'criado_em']);
        $flashcard = $respostas->where('tipo', 'flashcard');
        $quiz = $respostas->where('tipo', 'quiz');

        $cardsCriados = Flashcard::where('usuario_id', $id)->whereBetween('criado_em', $periodo)->pluck('criado_em');
        $prompts = Prompt::where('usuario_id', $id)->whereBetween('criado_em', $periodo)->get(['id', 'criado_em']);
        $pronuncias = PronunciaTentativa::where('usuario_id', $id)->whereBetween('criado_em', $periodo)->get(['nota', 'criado_em']);

        // Dia ativo: qualquer dia com revisão, quiz, pronúncia, card ou prompt criado
        $dias = collect()
            ->merge($respostas->pluck('criado_em'))
            ->merge($cardsCriados)
            ->merge($prompts->pluck('criado_em'))
            ->merge($pronuncias->pluck('criado_em'))
            ->filter()
            ->map(fn ($data) => CarbonImmutable::parse($data)->format('Y-m-d'))
            ->unique();

        $nivelamentos = Nivelamento::where('usuario_id', $id)
            ->whereNotNull('concluido_em')
            ->where('concluido_em', '<=', $fim)
            ->orderBy('concluido_em')
            ->get(['nivel_resultado', 'concluido_em']);
        // O inicial é o primeiro teste feito até o fim do estudo; o final, o último feito dentro dele
        $inicial = $nivelamentos->first();
        $final = $nivelamentos->filter(fn ($n) => $n->getAttribute('concluido_em')->gte($inicio))->last();

        $progresso = ProgressoUsuario::where('usuario_id', $id)->first();
        $notaMedia = $pronuncias->avg('nota');

        return [
            'participante' => $codigo,
            'nivel_ingles_atual' => (string) $user->getAttribute('nivel_ingles'),
            'nivelamento_inicial' => $inicial?->getAttribute('nivel_resultado'),
            'nivelamento_final' => $final && $final !== $inicial ? $final->getAttribute('nivel_resultado') : null,
            'dias_ativos' => $dias->count(),
            'flashcards_criados' => $cardsCriados->count(),
            'revisoes_flashcard' => $flashcard->count(),
            'acerto_flashcard_pct' => $this->taxa($flashcard),
            'respostas_quiz' => $quiz->count(),
            'acerto_quiz_pct' => $this->taxa($quiz),
            'acerto_primeira_metade_pct' => $this->taxa($respostas->filter(fn ($r) => $r->getAttribute('criado_em')->lt($meio))),
            'acerto_segunda_metade_pct' => $this->taxa($respostas->filter(fn ($r) => $r->getAttribute('criado_em')->gte($meio))),
            'prompts_criados' => $prompts->count(),
            'frases_semelhantes' => $prompts->isEmpty() ? 0 : FraseSemelhante::whereIn('prompt_id', $prompts->pluck('id'))->count(),
            'pronuncia_tentativas' => $pronuncias->count(),
            'pronuncia_nota_media' => $notaMedia === null ? null : round((float) $notaMedia, 1),
            'maior_sequencia' => (int) max($progresso?->getAttribute('maior_sequencia') ?? 0, $progresso?->getAttribute('sequencia_atual') ?? 0),
            'conquistas_no_periodo' => ConquistaUsuario::where('usuario_id', $id)->whereBetween('conquistada_em', $periodo)->count(),
        ];
    }

    /**
     * Percentual de acertos, com uma casa; vazio quando não houve resposta.
     *
     * @param  Collection<int, RespostaUsuario>  $respostas
     */
    private function taxa(Collection $respostas): ?float
    {
        if ($respostas->isEmpty()) {
            return null;
        }

        return round($respostas->filter(fn ($r) => (bool) $r->getAttribute('correto'))->count() / $respostas->count() * 100, 1);
    }
}

<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\QuizQuestao;
use App\Models\RespostaUsuario;
use Illuminate\Support\Facades\DB;
use Normalizer;

/**
 * Correção das respostas de um quiz, sempre no servidor (quem joga não recebe o gabarito antes
 * de responder). O chamador valida o acesso ao quiz antes.
 *
 * Resposta de uma questão: { questao_id, alternativa_id } (múltipla escolha) ou
 * { questao_id, resposta } (completar).
 */
class QuizTentativaService
{
    public function __construct(
        private QuizQuestaoService $questoes,
        private ProgressoService $progresso,
        private ConquistaService $conquistas,
    ) {}

    /**
     * Corrige uma questão sem gravar nada (feedback imediato durante o jogo).
     *
     * @param  array<string, mixed>  $resposta
     * @return array<string, mixed>
     */
    public function verificar(int $quizId, int $questaoId, array $resposta): array
    {
        return $this->corrigir($this->questoes->buscarEntidade($quizId, $questaoId), $resposta);
    }

    /**
     * Corrige todas as questões, grava as respostas e soma o quiz no progresso (uma vez por usuário).
     * Questão sem resposta conta como errada.
     *
     * @return array<string, mixed>
     */
    public function finalizar(int $usuarioId, int $quizId, mixed $respostas): array
    {
        if (! is_array($respostas)) {
            throw new ValidationException('Respostas inválidas', [
                'respostas' => ['array' => 'Envie as respostas como uma lista'],
            ]);
        }

        $questoes = $this->questoes->buscarEntidades($quizId);
        if ($questoes->isEmpty()) {
            throw new ValidationException('Este quiz ainda não tem questões', [
                'questoes' => ['_empty' => 'Adicione questões ao quiz antes de jogar'],
            ]);
        }

        $porQuestao = [];
        foreach ($respostas as $resposta) {
            if (is_array($resposta) && isset($resposta['questao_id']) && is_numeric($resposta['questao_id'])) {
                $porQuestao[(int) $resposta['questao_id']] = $resposta;
            }
        }

        $correcao = $questoes
            ->map(fn (QuizQuestao $questao) => $this->corrigir($questao, $porQuestao[(int) $questao->id] ?? []))
            ->values()
            ->all();

        $acertos = count(array_filter($correcao, fn (array $item) => $item['correta']));
        $total = count($correcao);

        if ($total > 0 && $acertos === $total) {
            $this->conquistas->conceder($usuarioId, 'quiz_gabaritado');
        }

        $primeiraConclusao = DB::transaction(function () use ($usuarioId, $quizId, $correcao) {
            // quizes_concluidos conta cada quiz uma vez por usuário; jogar de novo só registra as respostas
            $jaConcluiu = RespostaUsuario::where([
                'usuario_id' => $usuarioId,
                'tipo' => 'quiz',
                'referencia_id' => $quizId,
            ])->exists();

            foreach ($correcao as $item) {
                RespostaUsuario::create([
                    'usuario_id' => $usuarioId,
                    'tipo' => 'quiz',
                    'referencia_id' => $quizId,
                    'correto' => $item['correta'],
                ]);
            }

            if (! $jaConcluiu) {
                $this->progresso->incrementarQuizes($usuarioId, 1);
            }

            return ! $jaConcluiu;
        });

        return [
            'quiz_id' => $quizId,
            'primeira_conclusao' => $primeiraConclusao,
            'total' => $total,
            'acertos' => $acertos,
            'erros' => $total - $acertos,
            'percentual' => (int) round($acertos / $total * 100),
            'correcao' => $correcao,
        ];
    }

    /**
     * @param  array<string, mixed>  $resposta
     * @return array<string, mixed>
     */
    public function corrigir(QuizQuestao $questao, array $resposta): array
    {
        $alternativaId = isset($resposta['alternativa_id']) && is_numeric($resposta['alternativa_id'])
            ? (int) $resposta['alternativa_id']
            : null;
        $texto = isset($resposta['resposta']) && is_scalar($resposta['resposta'])
            ? trim((string) $resposta['resposta'])
            : null;

        $item = [
            'questao_id' => (int) $questao->id,
            'tipo' => (string) $questao->tipo,
            'correta' => false,
            'respondida' => false,
            'alternativa_id' => null,
            'resposta' => null,
            'alternativa_correta_id' => null,
            'resposta_esperada' => null,
            'explicacao' => $questao->explicacao,
        ];

        if ($questao->tipo === 'completar') {
            $item['resposta'] = $texto;
            $item['respondida'] = $texto !== null && $texto !== '';
            $item['resposta_esperada'] = $questao->resposta_esperada;
            $item['correta'] = $item['respondida']
                && self::normalizarTexto((string) $texto) === self::normalizarTexto((string) $questao->resposta_esperada);

            return $item;
        }

        foreach ($questao->alternativas as $alternativa) {
            if ($alternativa->correta) {
                $item['alternativa_correta_id'] = (int) $alternativa->id;
            }
        }

        $item['alternativa_id'] = $alternativaId;
        $item['respondida'] = $alternativaId !== null;
        $item['correta'] = $alternativaId !== null && $alternativaId === $item['alternativa_correta_id'];

        return $item;
    }

    /**
     * Texto para comparação: sem acentos, sem espaços extras e em minúsculas.
     */
    public static function normalizarTexto(string $texto): string
    {
        $texto = Normalizer::normalize($texto, Normalizer::FORM_D) ?: $texto;
        $texto = (string) preg_replace('/\p{Mn}+/u', '', $texto);
        $texto = (string) preg_replace('/\s+/u', ' ', $texto);

        return mb_strtolower(trim($texto));
    }
}

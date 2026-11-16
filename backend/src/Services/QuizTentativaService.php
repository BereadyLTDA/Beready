<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Model\Entity\QuizQuestao;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
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
    ) {
    }

    /**
     * Corrige uma questão sem gravar nada (feedback imediato durante o jogo).
     */
    public function verificar(int $quizId, int $questaoId, array $resposta): array
    {
        return $this->corrigir($this->questoes->buscarEntidade($quizId, $questaoId), $resposta);
    }

    /**
     * Corrige a tentativa inteira: questões sem resposta contam como erradas. Grava uma linha em
     * respostas_usuario por questão (tipo 'quiz', referencia_id = quiz) e soma 1 em quizes_concluidos
     * só na primeira vez que o usuário conclui o quiz.
     *
     * @return array{quiz_id: int, primeira_conclusao: bool, total: int, acertos: int, erros: int, percentual: int, correcao: array}
     */
    public function finalizar(int $usuarioId, int $quizId, mixed $respostas): array
    {
        if (!is_array($respostas)) {
            throw new ValidationException('Respostas inválidas', [
                'respostas' => ['array' => 'Envie as respostas como uma lista'],
            ]);
        }

        $questoes = $this->questoes->buscarEntidades($quizId);
        if (!$questoes) {
            throw new ValidationException('Este quiz ainda não tem questões', [
                'questoes' => ['_empty' => 'Adicione questões ao quiz antes de jogar'],
            ]);
        }

        $porQuestao = [];
        foreach ($respostas as $resposta) {
            if (is_array($resposta) && isset($resposta['questao_id']) && is_numeric($resposta['questao_id'])) {
                $porQuestao[(int)$resposta['questao_id']] = $resposta;
            }
        }

        $correcao = array_map(
            fn (QuizQuestao $questao) => $this->corrigir($questao, $porQuestao[(int)$questao->id] ?? []),
            $questoes
        );
        $acertos = count(array_filter($correcao, fn (array $item) => $item['correta']));
        $total = count($correcao);

        // Por referência: se o callback retornasse false, o transactional() desfaria a transação
        $primeiraConclusao = false;
        ConnectionManager::get('default')->transactional(function () use ($usuarioId, $quizId, $correcao, &$primeiraConclusao) {
            $tabela = TableRegistry::getTableLocator()->get('RespostasUsuario');

            // quizes_concluidos conta cada quiz uma vez por usuário; jogar de novo só registra as respostas
            $jaConcluiu = $tabela->exists([
                'usuario_id' => $usuarioId,
                'tipo' => 'quiz',
                'referencia_id' => $quizId,
            ]);

            foreach ($correcao as $item) {
                $tabela->saveOrFail($tabela->newEntity([
                    'usuario_id' => $usuarioId,
                    'tipo' => 'quiz',
                    'referencia_id' => $quizId,
                    'correto' => $item['correta'],
                ]));
            }

            if (!$jaConcluiu) {
                $this->progresso->incrementarQuizes($usuarioId, 1);
            }
            $primeiraConclusao = !$jaConcluiu;
        });

        return [
            'quiz_id' => $quizId,
            'primeira_conclusao' => $primeiraConclusao,
            'total' => $total,
            'acertos' => $acertos,
            'erros' => $total - $acertos,
            'percentual' => (int)round($acertos / $total * 100),
            'correcao' => $correcao,
        ];
    }

    /**
     * @return array{questao_id: int, tipo: string, correta: bool, respondida: bool, alternativa_id: ?int, resposta: ?string, alternativa_correta_id: ?int, resposta_esperada: ?string, explicacao: ?string}
     */
    public function corrigir(QuizQuestao $questao, array $resposta): array
    {
        $alternativaId = isset($resposta['alternativa_id']) && is_numeric($resposta['alternativa_id'])
            ? (int)$resposta['alternativa_id']
            : null;
        $texto = isset($resposta['resposta']) && is_scalar($resposta['resposta'])
            ? trim((string)$resposta['resposta'])
            : null;

        $item = [
            'questao_id' => (int)$questao->id,
            'tipo' => (string)$questao->tipo,
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
                && self::normalizarTexto($texto) === self::normalizarTexto((string)$questao->resposta_esperada);

            return $item;
        }

        foreach ($questao->quiz_alternativas ?? [] as $alternativa) {
            if ($alternativa->correta) {
                $item['alternativa_correta_id'] = (int)$alternativa->id;
            }
        }

        $item['alternativa_id'] = $alternativaId;
        $item['respondida'] = $alternativaId !== null;
        $item['correta'] = $alternativaId !== null && $alternativaId === $item['alternativa_correta_id'];

        return $item;
    }

    /**
     * Comparação da resposta "completar": sem diferenciar maiúsculas, acentos e espaços extras.
     */
    public static function normalizarTexto(string $texto): string
    {
        $texto = Normalizer::normalize($texto, Normalizer::FORM_D) ?: $texto;
        $texto = (string)preg_replace('/\p{Mn}+/u', '', $texto);
        $texto = (string)preg_replace('/\s+/u', ' ', $texto);

        return mb_strtolower(trim($texto));
    }
}

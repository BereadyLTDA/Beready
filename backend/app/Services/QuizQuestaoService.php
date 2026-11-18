<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Quiz;
use App\Models\QuizAlternativa;
use App\Models\QuizQuestao;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CRUD e validação das questões de um quiz. O chamador valida o dono do quiz antes.
 *
 * Regras:
 *  - 'multipla_escolha': de 2 a 6 alternativas com texto, exatamente 1 correta;
 *  - 'completar': resposta esperada obrigatória (a correção ignora maiúsculas, acentos e espaços extras).
 *
 * `quizes.total_questoes` é sempre a contagem das questões e é atualizado a cada alteração.
 * Sem gabarito (comGabarito = false) as questões não trazem `correta`, `resposta_esperada`
 * nem `explicacao`: é o formato enviado a quem vai jogar.
 */
class QuizQuestaoService
{
    public const TIPOS = ['multipla_escolha', 'completar'];

    public const MIN_ALTERNATIVAS = 2;

    public const MAX_ALTERNATIVAS = 6;

    public const MAX_QUESTOES = 100;

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(int $quizId, bool $comGabarito): array
    {
        return $this->buscarEntidades($quizId)
            ->map(fn (QuizQuestao $questao) => $this->formatar($questao, $comGabarito))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, QuizQuestao>
     */
    public function buscarEntidades(int $quizId)
    {
        return QuizQuestao::with('alternativas')
            ->where('quiz_id', $quizId)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();
    }

    public function buscarEntidade(int $quizId, int $questaoId): QuizQuestao
    {
        $questao = QuizQuestao::with('alternativas')->where('id', $questaoId)->where('quiz_id', $quizId)->first();
        if (! $questao) {
            throw new \RuntimeException('Questão não encontrada', 404);
        }

        return $questao;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function criar(int $quizId, array $data): array
    {
        $normalizada = $this->normalizar($data);

        $questao = DB::transaction(function () use ($quizId, $normalizada) {
            if (QuizQuestao::where('quiz_id', $quizId)->count() >= self::MAX_QUESTOES) {
                throw new ValidationException('Limite de questões do quiz atingido', [
                    'questoes' => ['maximo' => sprintf('Um quiz pode ter no máximo %d questões', self::MAX_QUESTOES)],
                ]);
            }

            $normalizada['ordem'] ??= $this->proximaOrdem($quizId);
            $questao = $this->inserir($quizId, $normalizada);
            $this->sincronizarTotal($quizId);

            return $questao;
        });

        return $this->formatar($this->buscarEntidade($quizId, (int) $questao->id), true);
    }

    /**
     * Atualização parcial: o que não vier no corpo mantém o valor atual.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function atualizar(int $quizId, int $questaoId, array $data): array
    {
        $atual = $this->formatar($this->buscarEntidade($quizId, $questaoId), true);
        $normalizada = $this->normalizar(array_merge($atual, array_intersect_key($data, array_flip([
            'tipo', 'enunciado', 'resposta_esperada', 'explicacao', 'ordem', 'alternativas',
        ]))));

        DB::transaction(function () use ($quizId, $questaoId, $normalizada) {
            $questao = $this->buscarEntidade($quizId, $questaoId);
            $questao->fill([
                'tipo' => $normalizada['tipo'],
                'enunciado' => $normalizada['enunciado'],
                'resposta_esperada' => $normalizada['resposta_esperada'],
                'explicacao' => $normalizada['explicacao'],
                'ordem' => $normalizada['ordem'] ?? $questao->ordem,
            ])->saveOrFail();

            QuizAlternativa::where('questao_id', $questaoId)->delete();
            $this->inserirAlternativas($questaoId, $normalizada['alternativas']);
        });

        return $this->formatar($this->buscarEntidade($quizId, $questaoId), true);
    }

    public function excluir(int $quizId, int $questaoId): void
    {
        $questao = $this->buscarEntidade($quizId, $questaoId);

        DB::transaction(function () use ($quizId, $questao) {
            $questao->delete();
            $this->sincronizarTotal($quizId);
        });
    }

    /**
     * Substitui todas as questões do quiz (a ordem é a posição na lista).
     *
     * @return list<array<string, mixed>>
     */
    public function substituirTodas(int $quizId, mixed $questoes): array
    {
        $normalizadas = $this->validarLista($questoes);

        DB::transaction(function () use ($quizId, $normalizadas) {
            QuizQuestao::where('quiz_id', $quizId)->delete();
            $this->inserirLista($quizId, $normalizadas);
        });

        return $this->listar($quizId, true);
    }

    /**
     * Valida uma lista de questões inteira antes de gravar qualquer uma.
     *
     * @return list<array<string, mixed>>
     */
    public function validarLista(mixed $questoes): array
    {
        if (! is_array($questoes) || ! array_is_list($questoes)) {
            throw new ValidationException('Questões inválidas', [
                'questoes' => ['array' => 'Envie as questões como uma lista'],
            ]);
        }

        if (count($questoes) > self::MAX_QUESTOES) {
            throw new ValidationException('Questões inválidas', [
                'questoes' => ['maximo' => sprintf('Um quiz pode ter no máximo %d questões', self::MAX_QUESTOES)],
            ]);
        }

        $normalizadas = [];
        $erros = [];
        foreach ($questoes as $i => $questao) {
            try {
                $normalizadas[] = $this->normalizar(is_array($questao) ? $questao : [], "questoes.{$i}.");
            } catch (ValidationException $e) {
                $erros += $e->getErrors();
            }
        }

        if ($erros) {
            throw new ValidationException('Há questões inválidas', $erros);
        }

        return $normalizadas;
    }

    /**
     * Grava questões já validadas, na ordem da lista.
     *
     * @param  list<array<string, mixed>>  $normalizadas
     */
    public function inserirLista(int $quizId, array $normalizadas): void
    {
        foreach (array_values($normalizadas) as $i => $questao) {
            $questao['ordem'] = $i + 1;
            $this->inserir($quizId, $questao);
        }

        $this->sincronizarTotal($quizId);
    }

    /**
     * Valida e normaliza uma questão. $prefixo identifica a questão nos erros de uma lista.
     *
     * @param  array<string, mixed>  $data
     * @return array{tipo: string, enunciado: string, resposta_esperada: string|null, explicacao: string|null, ordem: int|null, alternativas: list<array{texto: string, correta: bool}>}
     */
    public function normalizar(array $data, string $prefixo = ''): array
    {
        $erros = [];

        $tipo = $data['tipo'] ?? 'multipla_escolha';
        if (! is_string($tipo) || ! in_array($tipo, self::TIPOS, true)) {
            $erros[$prefixo.'tipo']['inList'] = 'Tipo inválido. Use multipla_escolha ou completar';
            $tipo = 'multipla_escolha';
        }

        $enunciado = $this->texto($data['enunciado'] ?? null);
        if ($enunciado === '') {
            $erros[$prefixo.'enunciado']['_empty'] = 'O enunciado é obrigatório';
        } elseif (mb_strlen($enunciado) > 2000) {
            $erros[$prefixo.'enunciado']['maxLength'] = 'O enunciado pode ter no máximo 2000 caracteres';
        }

        $explicacao = $this->texto($data['explicacao'] ?? null);
        if (mb_strlen($explicacao) > 2000) {
            $erros[$prefixo.'explicacao']['maxLength'] = 'A explicação pode ter no máximo 2000 caracteres';
        }

        $ordem = null;
        if (isset($data['ordem']) && $data['ordem'] !== '') {
            if (! is_numeric($data['ordem']) || (int) $data['ordem'] < 0) {
                $erros[$prefixo.'ordem']['integer'] = 'A ordem deve ser um número inteiro positivo';
            } else {
                $ordem = (int) $data['ordem'];
            }
        }

        $respostaEsperada = null;
        $alternativas = [];

        if ($tipo === 'completar') {
            $respostaEsperada = $this->texto($data['resposta_esperada'] ?? null);
            if ($respostaEsperada === '') {
                $erros[$prefixo.'resposta_esperada']['_empty'] = 'Informe a resposta esperada';
            } elseif (mb_strlen($respostaEsperada) > 500) {
                $erros[$prefixo.'resposta_esperada']['maxLength'] = 'A resposta esperada pode ter no máximo 500 caracteres';
            }
        } else {
            $recebidas = $data['alternativas'] ?? [];
            if (! is_array($recebidas)) {
                $recebidas = [];
            }

            $corretas = 0;
            foreach (array_values($recebidas) as $i => $alternativa) {
                $alternativa = is_array($alternativa) ? $alternativa : [];
                $texto = $this->texto($alternativa['texto'] ?? null);
                $correta = filter_var($alternativa['correta'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($texto === '') {
                    $erros[$prefixo."alternativas.{$i}.texto"]['_empty'] = 'A alternativa precisa de um texto';
                } elseif (mb_strlen($texto) > 2000) {
                    $erros[$prefixo."alternativas.{$i}.texto"]['maxLength'] = 'A alternativa pode ter no máximo 2000 caracteres';
                }

                $corretas += $correta ? 1 : 0;
                $alternativas[] = ['texto' => $texto, 'correta' => $correta];
            }

            if (count($alternativas) < self::MIN_ALTERNATIVAS) {
                $erros[$prefixo.'alternativas']['minimo'] = sprintf('A questão precisa de pelo menos %d alternativas', self::MIN_ALTERNATIVAS);
            } elseif (count($alternativas) > self::MAX_ALTERNATIVAS) {
                $erros[$prefixo.'alternativas']['maximo'] = sprintf('A questão pode ter no máximo %d alternativas', self::MAX_ALTERNATIVAS);
            }

            if (count($alternativas) >= self::MIN_ALTERNATIVAS && $corretas !== 1) {
                $erros[$prefixo.'alternativas']['umaCorreta'] = 'Marque exatamente uma alternativa correta';
            }
        }

        if ($erros) {
            throw new ValidationException('Questão inválida', $erros);
        }

        return [
            'tipo' => $tipo,
            'enunciado' => $enunciado,
            'resposta_esperada' => $respostaEsperada,
            'explicacao' => $explicacao === '' ? null : $explicacao,
            'ordem' => $ordem,
            'alternativas' => $alternativas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatar(QuizQuestao $questao, bool $comGabarito): array
    {
        $alternativas = $questao->alternativas->map(function (QuizAlternativa $alternativa) use ($comGabarito) {
            $item = [
                'id' => (int) $alternativa->id,
                'texto' => (string) $alternativa->texto,
                'ordem' => (int) $alternativa->ordem,
            ];
            if ($comGabarito) {
                $item['correta'] = (bool) $alternativa->correta;
            }

            return $item;
        })->values()->all();

        $dados = [
            'id' => (int) $questao->id,
            'quiz_id' => (int) $questao->quiz_id,
            'tipo' => (string) $questao->tipo,
            'enunciado' => (string) $questao->enunciado,
            'ordem' => (int) $questao->ordem,
            'alternativas' => $questao->tipo === 'multipla_escolha' ? $alternativas : [],
        ];

        if ($comGabarito) {
            $dados['resposta_esperada'] = $questao->resposta_esperada;
            $dados['explicacao'] = $questao->explicacao;
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $normalizada
     */
    private function inserir(int $quizId, array $normalizada): QuizQuestao
    {
        $questao = QuizQuestao::create([
            'quiz_id' => $quizId,
            'tipo' => $normalizada['tipo'],
            'enunciado' => $normalizada['enunciado'],
            'resposta_esperada' => $normalizada['resposta_esperada'],
            'explicacao' => $normalizada['explicacao'],
            'ordem' => $normalizada['ordem'] ?? 0,
        ]);

        $this->inserirAlternativas((int) $questao->id, $normalizada['alternativas']);

        return $questao;
    }

    /**
     * @param  list<array{texto: string, correta: bool}>  $alternativas
     */
    private function inserirAlternativas(int $questaoId, array $alternativas): void
    {
        foreach (array_values($alternativas) as $i => $alternativa) {
            QuizAlternativa::create([
                'questao_id' => $questaoId,
                'texto' => $alternativa['texto'],
                'correta' => $alternativa['correta'],
                'ordem' => $i + 1,
            ]);
        }
    }

    private function proximaOrdem(int $quizId): int
    {
        return (int) QuizQuestao::where('quiz_id', $quizId)->max('ordem') + 1;
    }

    private function sincronizarTotal(int $quizId): void
    {
        Quiz::whereKey($quizId)->update(['total_questoes' => QuizQuestao::where('quiz_id', $quizId)->count()]);
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}

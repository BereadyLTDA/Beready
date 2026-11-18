<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Flashcard;
use App\Models\FlashcardTag;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Gera um quiz de múltipla escolha a partir dos flashcards do usuário: a frente vira o
 * enunciado, o verso é a alternativa correta e versos de outros flashcards do usuário são
 * os distratores (até 3 por questão).
 *
 * Exige pelo menos MIN_FLASHCARDS flashcards com respostas (versos) diferentes, para que toda
 * questão tenha 1 correta + 3 distratores.
 */
class QuizGeradorService
{
    public const MIN_FLASHCARDS = 4;

    public const DISTRATORES = 3;

    public const QUANTIDADE_PADRAO = 10;

    public const QUANTIDADE_MAXIMA = 50;

    public const NIVEIS = ['iniciante', 'intermediario', 'avancado'];

    /**
     * @param  \Closure(array): array|null  $embaralhar  troca a ordem de uma lista (injetável nos testes)
     */
    public function __construct(
        private QuizQuestaoService $questoes,
        private QuizService $quizes,
        private ?\Closure $embaralhar = null,
    ) {
        $this->embaralhar ??= function (array $lista): array {
            shuffle($lista);

            return $lista;
        };
    }

    /**
     * @param  array{quantidade?: mixed, nivel?: mixed, tag_id?: mixed, titulo?: mixed}  $data
     * @return array quiz criado, com as questões (com gabarito: o dono é quem gera)
     */
    public function gerar(int $usuarioId, array $data): array
    {
        [$quantidade, $nivel, $tagId, $titulo] = $this->validar($usuarioId, $data);

        $todos = Flashcard::query()
            ->select(['id', 'frente', 'verso', 'nivel_dificuldade'])
            ->where('usuario_id', $usuarioId)
            ->get()
            ->all();

        // Distratores: versos distintos (sem diferenciar maiúsculas/acentos/espaços) de todos os flashcards
        $versos = [];
        foreach ($todos as $flashcard) {
            $verso = trim((string) $flashcard->verso);
            $chave = QuizTentativaService::normalizarTexto($verso);
            if ($chave !== '' && trim((string) $flashcard->frente) !== '') {
                $versos[$chave] ??= $verso;
            }
        }

        if (count($versos) < self::MIN_FLASHCARDS) {
            throw new ValidationException(
                sprintf(
                    'São necessários pelo menos %d flashcards com respostas diferentes para gerar um quiz (você tem %d)',
                    self::MIN_FLASHCARDS,
                    count($versos)
                ),
                ['flashcards' => ['minimo' => sprintf('Crie pelo menos %d flashcards com respostas diferentes', self::MIN_FLASHCARDS)]]
            );
        }

        $candidatos = array_values(array_filter($todos, function ($flashcard) use ($nivel) {
            return trim((string) $flashcard->frente) !== ''
                && trim((string) $flashcard->verso) !== ''
                && ($nivel === null || $flashcard->nivel_dificuldade === $nivel);
        }));

        if ($tagId !== null) {
            $comTag = FlashcardTag::where('tag_id', $tagId)
                ->pluck('flashcard_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $candidatos = array_values(array_filter(
                $candidatos,
                fn ($flashcard) => in_array((int) $flashcard->id, $comTag, true)
            ));
        }

        if (! $candidatos) {
            throw new ValidationException('Nenhum flashcard encontrado com os filtros escolhidos', [
                'flashcards' => ['_empty' => 'Nenhum flashcard encontrado com os filtros escolhidos'],
            ]);
        }

        $selecionados = array_slice(($this->embaralhar)($candidatos), 0, $quantidade);
        $questoes = array_map(fn ($flashcard) => $this->montarQuestao($flashcard, $versos), $selecionados);
        $normalizadas = $this->questoes->validarLista($questoes);

        return DB::transaction(function () use ($usuarioId, $titulo, $nivel, $normalizadas) {
            $quiz = $this->quizes->create([
                'usuario_id' => $usuarioId,
                'titulo' => $titulo,
                'descricao' => null,
                'tipo_criacao' => 'flashcards',
                'nivel_dificuldade' => $nivel ?? 'iniciante',
                'total_questoes' => 0,
                'publico' => false,
            ]);

            $this->questoes->inserirLista((int) $quiz['id'], $normalizadas);

            $quiz = $this->quizes->getQuizById((int) $quiz['id']);
            $quiz['questoes'] = $this->questoes->listar((int) $quiz['id'], true);

            return $quiz;
        });
    }

    /**
     * @return array{0: int, 1: ?string, 2: ?int, 3: string}
     */
    private function validar(int $usuarioId, array $data): array
    {
        $erros = [];

        $quantidade = $data['quantidade'] ?? self::QUANTIDADE_PADRAO;
        if (! is_numeric($quantidade) || (int) $quantidade != $quantidade
            || (int) $quantidade < 1 || (int) $quantidade > self::QUANTIDADE_MAXIMA) {
            $erros['quantidade']['range'] = sprintf('A quantidade deve ser um número entre 1 e %d', self::QUANTIDADE_MAXIMA);
        }

        $nivel = $data['nivel'] ?? null;
        if ($nivel === '') {
            $nivel = null;
        }
        if ($nivel !== null && (! is_string($nivel) || ! in_array($nivel, self::NIVEIS, true))) {
            $erros['nivel']['inList'] = 'Nível inválido. Use iniciante, intermediario ou avancado';
        }

        $tagId = $data['tag_id'] ?? null;
        if ($tagId === '') {
            $tagId = null;
        }
        if ($tagId !== null && (! is_numeric($tagId) || (int) $tagId <= 0)) {
            $erros['tag_id']['integer'] = 'Tag inválida';
        }

        $titulo = isset($data['titulo']) && is_scalar($data['titulo']) ? trim((string) $data['titulo']) : '';
        if (mb_strlen($titulo) > 200) {
            $erros['titulo']['maxLength'] = 'O título pode ter no máximo 200 caracteres';
        }

        if ($erros) {
            throw new ValidationException('Dados inválidos para gerar o quiz', $erros);
        }

        if ($tagId !== null) {
            // Tag de outro usuário responde 404, como se não existisse (tags de sistema valem para todos)
            $tag = Tag::find((int) $tagId);
            if (! $tag || (! $tag->tag_sistema && (int) $tag->criado_por !== $usuarioId)) {
                throw new \RuntimeException('Tag não encontrada', 404);
            }
        }

        return [
            (int) $quantidade,
            $nivel,
            $tagId === null ? null : (int) $tagId,
            $titulo !== '' ? $titulo : 'Quiz dos meus flashcards',
        ];
    }

    /**
     * @param  array<string, string>  $versos  versos distintos do usuário, indexados pela forma normalizada
     */
    private function montarQuestao($flashcard, array $versos): array
    {
        $correta = trim((string) $flashcard->verso);
        $chaveCorreta = QuizTentativaService::normalizarTexto($correta);

        $outros = array_values(array_diff_key($versos, [$chaveCorreta => true]));
        $distratores = array_slice(($this->embaralhar)($outros), 0, self::DISTRATORES);

        $alternativas = [['texto' => $correta, 'correta' => true]];
        foreach ($distratores as $distrator) {
            $alternativas[] = ['texto' => $distrator, 'correta' => false];
        }

        return [
            'tipo' => 'multipla_escolha',
            'enunciado' => trim((string) $flashcard->frente),
            'explicacao' => null,
            'alternativas' => ($this->embaralhar)($alternativas),
        ];
    }
}

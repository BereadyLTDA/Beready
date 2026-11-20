<?php

namespace App\Services;

use App\Exceptions\NivelamentoBloqueadoException;
use App\Exceptions\ValidationException;
use App\Models\Nivelamento;
use App\Models\NivelamentoQuestao;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Teste de nivelamento adaptativo: começa no B1, sobe um nível depois de 2 acertos seguidos e desce
 * depois de 2 erros seguidos. Termina em TOTAL_QUESTOES; o nível em que o aluno está é o resultado.
 * Toda a correção é feita aqui: a alternativa correta nunca vai para o navegador.
 */
class NivelamentoService
{
    public const NIVEIS = ['A1', 'A2', 'B1', 'B2', 'C1'];

    public const NIVEL_INICIAL = 'B1';

    public const TOTAL_QUESTOES = 12;

    public const SEGUIDAS_PARA_MUDAR = 2;

    public const DIAS_PARA_REFAZER = 30;

    /** Nível do CEFR => nível usado no resto do app (users.nivel_ingles) */
    public const PARA_NIVEL_INGLES = [
        'A1' => 'iniciante',
        'A2' => 'iniciante',
        'B1' => 'intermediario',
        'B2' => 'intermediario',
        'C1' => 'avancado',
    ];

    /**
     * Começa um teste, ou continua o que estiver em andamento.
     *
     * @return array<string, mixed>
     */
    public function iniciar(int $usuarioId): array
    {
        $emAndamento = Nivelamento::where('usuario_id', $usuarioId)->whereNull('concluido_em')->latest('iniciado_em')->first();
        if ($emAndamento) {
            return $this->estado($emAndamento);
        }

        $ultimo = $this->ultimoConcluido($usuarioId);
        if ($ultimo && $ultimo->getAttribute('concluido_em')->gt(now()->subDays(self::DIAS_PARA_REFAZER))) {
            $quando = $ultimo->getAttribute('concluido_em')->addDays(self::DIAS_PARA_REFAZER);
            throw new NivelamentoBloqueadoException('Você poderá refazer o teste a partir de '.$quando->format('d/m/Y').'.');
        }

        $nivelamento = Nivelamento::create(['usuario_id' => $usuarioId, 'nivel_atual' => self::NIVEL_INICIAL]);
        $nivelamento->forceFill(['respostas' => [], 'questao_atual_id' => $this->sortearQuestao(self::NIVEL_INICIAL, [])])->saveOrFail();

        return $this->estado($nivelamento);
    }

    /**
     * Corrige a resposta da questão atual e avança o teste.
     *
     * @return array<string, mixed>
     */
    public function responder(int $usuarioId, int $nivelamentoId, mixed $alternativa): array
    {
        return DB::transaction(function () use ($usuarioId, $nivelamentoId, $alternativa) {
            $nivelamento = Nivelamento::where('usuario_id', $usuarioId)->whereKey($nivelamentoId)->lockForUpdate()->first();
            if (! $nivelamento) {
                throw new ModelNotFoundException;
            }
            if ($nivelamento->getAttribute('concluido_em') !== null) {
                throw new ValidationException('Este teste já foi concluído', ['nivelamento' => ['concluido' => 'Teste concluído']]);
            }

            $questao = NivelamentoQuestao::findOrFail((int) $nivelamento->getAttribute('questao_atual_id'));
            $opcoes = count((array) $questao->getAttribute('alternativas'));
            if (! is_numeric($alternativa) || (int) $alternativa < 0 || (int) $alternativa >= $opcoes) {
                throw new ValidationException('Escolha uma das alternativas', ['alternativa' => ['invalida' => 'Alternativa inválida']]);
            }

            $correta = (int) $alternativa === (int) $questao->getAttribute('correta');
            $nivel = (string) $nivelamento->getAttribute('nivel_atual');
            $respostas = (array) $nivelamento->getAttribute('respostas');
            $respostas[] = ['questao_id' => (int) $questao->getKey(), 'nivel' => $nivel, 'correta' => $correta];

            [$nivel, $acertos, $erros] = $this->proximoNivel(
                $nivel,
                $correta,
                (int) $nivelamento->getAttribute('acertos_seguidos'),
                (int) $nivelamento->getAttribute('erros_seguidos'),
            );

            $nivelamento->forceFill([
                'nivel_atual' => $nivel,
                'acertos_seguidos' => $acertos,
                'erros_seguidos' => $erros,
                'respostas' => $respostas,
            ]);

            if (count($respostas) >= self::TOTAL_QUESTOES) {
                $nivelamento->forceFill([
                    'nivel_resultado' => $nivel,
                    'concluido_em' => now(),
                    'questao_atual_id' => null,
                ])->saveOrFail();
                // O resultado vira o nível do aluno no resto do app
                User::whereKey($usuarioId)->update(['nivel_ingles' => self::PARA_NIVEL_INGLES[$nivel]]);
            } else {
                $jaFeitas = array_column($respostas, 'questao_id');
                $nivelamento->forceFill(['questao_atual_id' => $this->sortearQuestao($nivel, $jaFeitas)])->saveOrFail();
            }

            return ['correta' => $correta] + $this->estado($nivelamento);
        });
    }

    /**
     * Último resultado e quando dá para refazer (null se nunca concluiu).
     *
     * @return array<string, mixed>|null
     */
    public function resultado(int $usuarioId): ?array
    {
        $ultimo = $this->ultimoConcluido($usuarioId);
        if (! $ultimo) {
            return null;
        }

        $nivel = (string) $ultimo->getAttribute('nivel_resultado');
        $respostas = (array) $ultimo->getAttribute('respostas');

        return [
            'nivel' => $nivel,
            'nivel_ingles' => self::PARA_NIVEL_INGLES[$nivel],
            'acertos' => count(array_filter($respostas, fn ($r) => ! empty($r['correta']))),
            'total' => count($respostas),
            'concluido_em' => $ultimo->getAttribute('concluido_em')->toAtomString(),
            'pode_refazer_em' => $ultimo->getAttribute('concluido_em')->addDays(self::DIAS_PARA_REFAZER)->toAtomString(),
        ];
    }

    /**
     * Regra adaptativa. Devolve [nível, acertos seguidos, erros seguidos].
     *
     * @return array{0: string, 1: int, 2: int}
     */
    public function proximoNivel(string $nivel, bool $correta, int $acertosSeguidos, int $errosSeguidos): array
    {
        $i = (int) array_search($nivel, self::NIVEIS, true);
        if ($correta) {
            $acertosSeguidos++;
            $errosSeguidos = 0;
            if ($acertosSeguidos >= self::SEGUIDAS_PARA_MUDAR && $i < count(self::NIVEIS) - 1) {
                return [self::NIVEIS[$i + 1], 0, 0];
            }
        } else {
            $errosSeguidos++;
            $acertosSeguidos = 0;
            if ($errosSeguidos >= self::SEGUIDAS_PARA_MUDAR && $i > 0) {
                return [self::NIVEIS[$i - 1], 0, 0];
            }
        }

        return [$nivel, $acertosSeguidos, $errosSeguidos];
    }

    /**
     * @return array<string, mixed>
     */
    private function estado(Nivelamento $nivelamento): array
    {
        $respondidas = count((array) $nivelamento->getAttribute('respostas'));
        $concluido = $nivelamento->getAttribute('concluido_em') !== null;
        $questao = $concluido ? null : NivelamentoQuestao::find((int) $nivelamento->getAttribute('questao_atual_id'));

        return [
            'id' => (int) $nivelamento->getKey(),
            'concluido' => $concluido,
            'respondidas' => $respondidas,
            'total' => self::TOTAL_QUESTOES,
            // Sem "correta" e sem o nível: o aluno não sabe em que nível está até o fim
            'questao' => $questao ? [
                'id' => (int) $questao->getKey(),
                'enunciado' => (string) $questao->getAttribute('enunciado'),
                'alternativas' => array_values((array) $questao->getAttribute('alternativas')),
            ] : null,
            'resultado' => $concluido ? $this->resultado((int) $nivelamento->getAttribute('usuario_id')) : null,
        ];
    }

    /**
     * Questão ainda não feita do nível; se o nível acabou, a do nível mais próximo.
     *
     * @param  list<int>  $jaFeitas
     */
    private function sortearQuestao(string $nivel, array $jaFeitas): int
    {
        $i = (int) array_search($nivel, self::NIVEIS, true);
        // Ordem de busca: o próprio nível, depois os vizinhos alternando (abaixo, acima, ...)
        $ordem = [$nivel];
        for ($d = 1; $d < count(self::NIVEIS); $d++) {
            foreach ([$i - $d, $i + $d] as $j) {
                if (isset(self::NIVEIS[$j])) {
                    $ordem[] = self::NIVEIS[$j];
                }
            }
        }

        foreach ($ordem as $n) {
            $id = NivelamentoQuestao::where('nivel', $n)->whereNotIn('id', $jaFeitas ?: [0])->inRandomOrder()->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        throw new \RuntimeException('Não há questões de nivelamento cadastradas');
    }

    private function ultimoConcluido(int $usuarioId): ?Nivelamento
    {
        return Nivelamento::where('usuario_id', $usuarioId)->whereNotNull('concluido_em')->latest('concluido_em')->first();
    }
}

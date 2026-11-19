<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\FraseSemelhante;
use App\Models\Prompt;
use App\Models\PronunciaTentativa;
use App\Models\Traducao;

/**
 * Exercício "Repita a frase": escolhe frases em inglês do próprio aluno e dá a nota comparando a
 * frase esperada com o que o reconhecimento de voz do navegador entendeu.
 */
class PronunciaService
{
    public const MAX_CARACTERES = 300;

    /**
     * Usadas quando o aluno ainda não tem frases em inglês suficientes.
     */
    private const FRASES_BASICAS = [
        'Nice to meet you.',
        'How are you doing today?',
        'Could you help me, please?',
        'I would like a cup of coffee.',
        'Where is the nearest station?',
        'What time does the meeting start?',
        'I am learning English every day.',
        'Can you speak a little slower?',
        'Thank you very much for your help.',
        'See you tomorrow morning.',
        'I have been working here for two years.',
        'The weather is really nice today.',
    ];

    /**
     * Até $limite frases em inglês: prompts em inglês, frases semelhantes deles e traduções para o
     * inglês. Completa com frases básicas.
     *
     * @return list<array{texto: string, origem: string}>
     */
    public function frasesParaPraticar(int $usuarioId, int $limite = 10): array
    {
        $limite = max(1, min(30, $limite));

        $promptsEmIngles = Prompt::where('usuario_id', $usuarioId)
            ->where('idioma_original', 'ilike', 'en%');

        $candidatas = [];
        foreach ((clone $promptsEmIngles)->pluck('texto_original') as $texto) {
            $candidatas[] = ['texto' => (string) $texto, 'origem' => 'prompt'];
        }
        $frases = FraseSemelhante::whereIn('prompt_id', (clone $promptsEmIngles)->select('id'))->pluck('frase_semelhante');
        foreach ($frases as $texto) {
            $candidatas[] = ['texto' => (string) $texto, 'origem' => 'frase'];
        }
        $traducoes = Traducao::whereIn('prompt_id', Prompt::where('usuario_id', $usuarioId)->select('id'))
            ->where('idioma_destino', 'ilike', 'en%')
            ->pluck('texto_traduzido');
        foreach ($traducoes as $texto) {
            $candidatas[] = ['texto' => (string) $texto, 'origem' => 'traducao'];
        }

        // Frases curtas o bastante para repetir de uma vez, sem repetidas
        $vistas = [];
        $escolhidas = [];
        shuffle($candidatas);
        foreach ($candidatas as $c) {
            $texto = trim($c['texto']);
            $chave = $this->normalizar($texto);
            $palavras = $chave === '' ? 0 : count(explode(' ', $chave));
            if ($palavras < 2 || $palavras > 25 || isset($vistas[$chave])) {
                continue;
            }
            $vistas[$chave] = true;
            $escolhidas[] = ['texto' => $texto, 'origem' => $c['origem']];
        }

        $basicas = self::FRASES_BASICAS;
        shuffle($basicas);
        foreach ($basicas as $texto) {
            if (count($escolhidas) >= $limite) {
                break;
            }
            if (! isset($vistas[$this->normalizar($texto)])) {
                $escolhidas[] = ['texto' => $texto, 'origem' => 'basica'];
            }
        }

        return array_slice($escolhidas, 0, $limite);
    }

    /**
     * Nota de 0 a 100 pela maior sequência comum de palavras (LCS), com as palavras esperadas
     * que não apareceram. Palavras a mais também baixam a nota.
     *
     * @return array{nota: int, faltando: list<string>}
     */
    public function avaliar(string $frase, string $reconhecido): array
    {
        $esperadas = $this->palavras($frase);
        $ditas = $this->palavras($reconhecido);
        $n = count($esperadas);
        $m = count($ditas);

        if ($n === 0 || $m === 0) {
            return ['nota' => 0, 'faltando' => $esperadas];
        }

        // Tabela da LCS por palavras
        $t = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $t[$i][$j] = $esperadas[$i] === $ditas[$j]
                    ? $t[$i + 1][$j + 1] + 1
                    : max($t[$i + 1][$j], $t[$i][$j + 1]);
            }
        }

        // Percorre a tabela para saber quais palavras esperadas foram ditas
        $faltando = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($esperadas[$i] === $ditas[$j]) {
                $i++;
                $j++;
            } elseif ($t[$i + 1][$j] >= $t[$i][$j + 1]) {
                $faltando[] = $esperadas[$i];
                $i++;
            } else {
                $j++;
            }
        }
        for (; $i < $n; $i++) {
            $faltando[] = $esperadas[$i];
        }

        $nota = (int) round(2 * $t[0][0] / ($n + $m) * 100);

        return ['nota' => max(0, min(100, $nota)), 'faltando' => $faltando];
    }

    /**
     * @return array{tentativa: array<string, mixed>, nota: int, faltando: list<string>}
     */
    public function registrar(int $usuarioId, string $frase, string $reconhecido): array
    {
        $frase = trim($frase);
        $reconhecido = trim($reconhecido);
        $erros = [];
        if ($frase === '' || mb_strlen($frase) > self::MAX_CARACTERES) {
            $erros['frase'] = ['invalida' => 'Informe a frase (até '.self::MAX_CARACTERES.' caracteres)'];
        }
        if (mb_strlen($reconhecido) > self::MAX_CARACTERES) {
            $erros['reconhecido'] = ['longo' => 'Texto reconhecido longo demais'];
        }
        if ($erros) {
            throw new ValidationException('Dados inválidos para registrar a pronúncia', $erros);
        }

        $avaliacao = $this->avaliar($frase, $reconhecido);
        $tentativa = PronunciaTentativa::create([
            'usuario_id' => $usuarioId,
            'frase' => $frase,
            'reconhecido' => $reconhecido,
            'nota' => $avaliacao['nota'],
        ]);

        return ['tentativa' => $tentativa->toArray()] + $avaliacao;
    }

    /**
     * @return array{total: int, media: int, melhor: int, recentes: list<array<string, mixed>>}
     */
    public function resumo(int $usuarioId): array
    {
        $base = PronunciaTentativa::where('usuario_id', $usuarioId);

        return [
            'total' => (clone $base)->count(),
            'media' => (int) round((float) (clone $base)->avg('nota')),
            'melhor' => (int) (clone $base)->max('nota'),
            'recentes' => array_values((clone $base)->orderByDesc('criado_em')->orderByDesc('id')->limit(10)->get()->toArray()),
        ];
    }

    /**
     * @return list<string>
     */
    private function palavras(string $texto): array
    {
        $normal = $this->normalizar($texto);

        return $normal === '' ? [] : explode(' ', $normal);
    }

    /**
     * Minúsculas, sem pontuação (mantém o apóstrofo de "don't") e com espaços simples.
     */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(str_replace(['’', '‘'], "'", $texto));
        $texto = (string) preg_replace("/[^\p{L}\p{N}' ]+/u", ' ', $texto);

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }
}

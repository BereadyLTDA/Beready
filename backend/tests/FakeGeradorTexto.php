<?php

namespace Tests;

use App\Contracts\GeradorTextoInterface;

/**
 * Gerador de texto falso: nenhuma chamada à IA sai dos testes. Registra o que foi pedido e
 * devolve respostas fixas; `$falha` simula um erro do provedor.
 *
 * Uso: $this->mockService(GeradorTextoInterface::class, fn () => $fake).
 */
class FakeGeradorTexto implements GeradorTextoInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $chamadas = [];

    public ?\Throwable $falha = null;

    /**
     * @var array{texto_traduzido: string, traducoes_alternativas: list<string>, pontuacao_confianca: float}
     */
    public array $traducao = [
        'texto_traduzido' => 'Onde fica a estação?',
        'traducoes_alternativas' => ['Onde é a estação?'],
        'pontuacao_confianca' => 0.97,
    ];

    public function traduzir(string $texto, string $idiomaOrigem, string $idiomaDestino, ?string $contexto): array
    {
        $this->chamadas[] = compact('texto', 'idiomaOrigem', 'idiomaDestino', 'contexto');
        if ($this->falha) {
            throw $this->falha;
        }

        return $this->traducao;
    }

    public function frasesSemelhantes(string $texto, string $idioma, int $quantidade, ?string $contexto): array
    {
        $this->chamadas[] = compact('texto', 'idioma', 'quantidade', 'contexto');
        if ($this->falha) {
            throw $this->falha;
        }

        $frases = [];
        for ($i = 1; $i <= $quantidade; $i++) {
            $frases[] = [
                'frase_semelhante' => "Frase gerada {$i}",
                'tipo_frase' => self::TIPOS_FRASE[($i - 1) % count(self::TIPOS_FRASE)],
                'nivel_dificuldade' => 'iniciante',
                'pontuacao_semelhante' => 0.8,
            ];
        }

        return $frases;
    }
}

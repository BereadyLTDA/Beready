<?php

namespace App\Contracts;

/**
 * Provedor de geração de texto por IA. A implementação de produção é o ClaudeGeradorTexto;
 * os testes usam um fake (mockService).
 *
 * Falhas do provedor chegam como \App\Exceptions\GeracaoException.
 */
interface GeradorTextoInterface
{
    public const TIPOS_FRASE = ['alternativa', 'sinonimo', 'exemplo', 'relacionada'];

    public const NIVEIS = ['iniciante', 'intermediario', 'avancado'];

    /**
     * Traduz um texto.
     *
     * @param  string|null  $contexto  onde o texto aparece (ajuda a escolher o sentido certo)
     * @return array{texto_traduzido: string, traducoes_alternativas: list<string>, pontuacao_confianca: float}
     *                                                                                                          pontuacao_confianca é a autoavaliação do modelo, de 0 a 1
     */
    public function traduzir(string $texto, string $idiomaOrigem, string $idiomaDestino, ?string $contexto): array;

    /**
     * Gera frases no mesmo idioma do texto, com sentido parecido, para praticar variações.
     *
     * @return list<array{frase_semelhante: string, tipo_frase: string, nivel_dificuldade: string, pontuacao_semelhante: float}>
     *                                                                                                                           tipo_frase em TIPOS_FRASE, nivel_dificuldade em NIVEIS, pontuacao_semelhante de 0 a 1
     */
    public function frasesSemelhantes(string $texto, string $idioma, int $quantidade, ?string $contexto): array;
}

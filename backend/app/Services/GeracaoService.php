<?php

namespace App\Services;

use App\Contracts\GeradorTextoInterface;
use App\Exceptions\GeracaoException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\TraducoesController;
use App\Models\FraseSemelhante;
use App\Models\Traducao;
use App\Support\Contador;

/**
 * Gera traduções e frases semelhantes de um prompt com IA e grava o resultado.
 * O chamador valida antes que o prompt pertence ao usuário.
 *
 * Cada geração custa uma chamada paga ao provedor, por isso há um teto diário por usuário
 * (config('beready.ia.limite_diario'), IA_LIMITE_DIARIO no .env), contado antes da chamada.
 */
class GeracaoService
{
    public const LIMITE_DIARIO_PADRAO = 50;

    public const FRASES_PADRAO = 5;

    public const FRASES_MAXIMO = 10;

    public const TEXTO_MAXIMO = 2000;

    /**
     * Nome gravado em traducoes.servico_traducao.
     */
    public const SERVICO = 'claude';

    public function __construct(
        private GeradorTextoInterface $gerador,
    ) {}

    /**
     * @param  array<string, mixed>  $prompt  registro do prompt (PromptRepository)
     * @param  array{idioma_destino?: mixed}  $data
     * @return array<string, mixed> tradução criada
     */
    public function gerarTraducao(array $prompt, int $usuarioId, array $data): array
    {
        $texto = $this->textoDoPrompt($prompt);

        $destino = trim((string) ($data['idioma_destino'] ?? ''));
        // Mesmo formato dos códigos usados no app (pt-BR, en-US, ja...); a coluna tem 10 caracteres
        if (! preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,4})?$/', $destino)) {
            throw new ValidationException('Idioma de destino inválido', [
                'idioma_destino' => ['invalido' => 'Informe o idioma de destino (ex.: pt-BR, en-US)'],
            ]);
        }

        $this->registrarUso($usuarioId);

        $gerada = $this->gerador->traduzir(
            $texto,
            (string) ($prompt['idioma_original'] ?? '') ?: 'detectar automaticamente',
            $destino,
            $this->contextoDoPrompt($prompt),
        );

        if ($gerada['texto_traduzido'] === '') {
            throw GeracaoException::falhou();
        }

        return Traducao::create(TraducoesController::prepararParaGravar([
            'prompt_id' => (int) $prompt['id'],
            'texto_traduzido' => $gerada['texto_traduzido'],
            'idioma_destino' => $destino,
            'pontuacao_confianca' => $gerada['pontuacao_confianca'],
            'servico_traducao' => self::SERVICO,
            'traducoes_alternativas' => $gerada['traducoes_alternativas'],
        ]))->toArray();
    }

    /**
     * @param  array<string, mixed>  $prompt  registro do prompt (PromptRepository)
     * @param  array{quantidade?: mixed}  $data
     * @return list<array<string, mixed>> frases criadas
     */
    public function gerarFrases(array $prompt, int $usuarioId, array $data): array
    {
        $texto = $this->textoDoPrompt($prompt);

        $quantidade = $data['quantidade'] ?? self::FRASES_PADRAO;
        if (! is_numeric($quantidade) || (int) $quantidade < 1 || (int) $quantidade > self::FRASES_MAXIMO) {
            throw new ValidationException('Quantidade de frases inválida', [
                'quantidade' => ['intervalo' => sprintf('Informe de 1 a %d frases', self::FRASES_MAXIMO)],
            ]);
        }

        $this->registrarUso($usuarioId);

        $geradas = $this->gerador->frasesSemelhantes(
            $texto,
            (string) ($prompt['idioma_original'] ?? '') ?: 'detectar automaticamente',
            (int) $quantidade,
            $this->contextoDoPrompt($prompt),
        );

        if (! $geradas) {
            throw GeracaoException::falhou();
        }

        $criadas = [];
        foreach ($geradas as $frase) {
            $criadas[] = FraseSemelhante::create(['prompt_id' => (int) $prompt['id']] + $frase)->toArray();
        }

        return $criadas;
    }

    /**
     * @param  array<string, mixed>  $prompt
     */
    private function textoDoPrompt(array $prompt): string
    {
        $texto = trim((string) ($prompt['texto_original'] ?? ''));

        if ($texto === '') {
            throw new ValidationException('O prompt não tem texto para gerar conteúdo', [
                'texto_original' => ['_empty' => 'O prompt não tem texto'],
            ]);
        }

        if (mb_strlen($texto) > self::TEXTO_MAXIMO) {
            throw new ValidationException('O texto do prompt é longo demais para a geração por IA', [
                'texto_original' => [
                    'maxLength' => sprintf('A geração por IA aceita textos de até %d caracteres', self::TEXTO_MAXIMO),
                ],
            ]);
        }

        return $texto;
    }

    /**
     * @param  array<string, mixed>  $prompt
     */
    private function contextoDoPrompt(array $prompt): ?string
    {
        $contexto = trim((string) ($prompt['contexto'] ?? ''));

        return $contexto === '' ? null : $contexto;
    }

    /**
     * Conta a geração no dia corrente. A tentativa conta mesmo se o provedor falhar depois: é a
     * chamada que custa, e assim uma falha repetida não vira um laço sem teto.
     */
    private function registrarUso(int $usuarioId): void
    {
        $limite = (int) (config('beready.ia.limite_diario') ?: self::LIMITE_DIARIO_PADRAO);
        $chave = sprintf('u%d_%s', $usuarioId, now()->format('Ymd'));

        if (Contador::incrementar('ia_uso_'.$chave, 86400) > $limite) {
            throw GeracaoException::limiteDiario($limite);
        }
    }
}

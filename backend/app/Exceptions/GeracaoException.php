<?php

namespace App\Exceptions;

/**
 * Falha na geração por IA. O código é o status HTTP devolvido ao cliente (ResourceErrorTrait).
 */
class GeracaoException extends \RuntimeException implements ErroComStatus
{
    public function status(): int
    {
        return $this->getCode();
    }

    /**
     * Sem ANTHROPIC_API_KEY (ou com uma chave recusada): o recurso está desligado neste ambiente.
     */
    public static function naoConfigurada(): self
    {
        return new self('A geração por IA não está disponível no momento', 503);
    }

    /**
     * Provedor sobrecarregado ou limitando requisições: vale tentar de novo em instantes.
     */
    public static function indisponivel(): self
    {
        return new self('O serviço de IA está ocupado. Tente novamente em instantes', 503);
    }

    /**
     * Erro do provedor ou resposta que não deu para aproveitar.
     */
    public static function falhou(): self
    {
        return new self('Não foi possível gerar o conteúdo. Tente novamente', 502);
    }

    /**
     * O modelo se recusou a processar o texto.
     */
    public static function recusada(): self
    {
        return new self('Não foi possível gerar conteúdo para este texto', 422);
    }

    public static function limiteDiario(int $limite): self
    {
        return new self(
            sprintf('Você atingiu o limite de %d gerações por IA hoje. Tente novamente amanhã', $limite),
            429,
        );
    }
}

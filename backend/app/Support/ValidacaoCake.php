<?php

namespace App\Support;

/**
 * Erros de validação no formato da versão em CakePHP ({ campo: { regra: mensagem } }) e a mensagem
 * resumida que os controllers do admin devolviam ("campo: mensagem; outro: mensagem").
 */
final class ValidacaoCake
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $erros = [];

    public function adicionar(string $campo, string $regra, string $mensagem): self
    {
        $this->erros[$campo][$regra] = $mensagem;

        return $this;
    }

    public function temErros(): bool
    {
        return $this->erros !== [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function erros(): array
    {
        return $this->erros;
    }

    public function resumo(): string
    {
        $partes = [];
        foreach ($this->erros as $campo => $mensagens) {
            $partes[] = $campo.': '.implode(', ', $mensagens);
        }

        return implode('; ', $partes);
    }
}

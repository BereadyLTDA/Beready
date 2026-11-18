<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Regra de agendamento da repetição espaçada (SM-2 simplificado). Sem acesso a banco:
 * recebe o estado atual do flashcard e devolve o próximo.
 *
 * Avaliações do estudo → nota do SM-2 (0 a 5):
 *  - 'errei' → 1: reinicia as repetições e o card volta em 10 minutos (no mesmo dia);
 *  - 'bom'   → 4: acerto com esforço;
 *  - 'facil' → 5: acerto fácil.
 *
 * Acerto: 1ª repetição = 1 dia, 2ª = 6 dias, depois intervalo anterior × facilidade.
 * A facilidade (ease) começa em 2.5, varia com a nota e nunca fica abaixo de 1.3.
 * Revisões com intervalo em dias vencem no início do dia (00:00), para o card contar como
 * "para revisar hoje" o dia todo, e não só a partir da hora em que foi estudado.
 */
class RepeticaoEspacadaService
{
    public const NOTAS = [
        'errei' => 1,
        'bom' => 4,
        'facil' => 5,
    ];

    public const FATOR_EASE_INICIAL = 2.5;

    public const FATOR_EASE_MINIMO = 1.3;

    public const MINUTOS_APOS_ERRO = 10;

    /**
     * @param  array{repeticoes?: int|null, intervalo_dias?: int|null, fator_ease?: float|string|null}  $estado
     * @return array{repeticoes: int, intervalo_dias: int, fator_ease: float, ultima_revisao: DateTimeImmutable, proxima_revisao: DateTimeImmutable}
     */
    public function calcular(array $estado, string $avaliacao, DateTimeInterface $agora): array
    {
        if (! isset(self::NOTAS[$avaliacao])) {
            throw new \InvalidArgumentException("Nota inválida. Use 'errei', 'bom' ou 'facil'");
        }

        $nota = self::NOTAS[$avaliacao];
        $agora = DateTimeImmutable::createFromInterface($agora);
        $repeticoes = max(0, (int) ($estado['repeticoes'] ?? 0));
        $intervalo = max(0, (int) ($estado['intervalo_dias'] ?? 0));
        $fatorEase = isset($estado['fator_ease']) && $estado['fator_ease'] !== ''
            ? (float) $estado['fator_ease']
            : self::FATOR_EASE_INICIAL;

        $fatorEase = $this->novoFatorEase($fatorEase, $nota);

        if ($nota < 3) {
            return [
                'repeticoes' => 0,
                'intervalo_dias' => 0,
                'fator_ease' => $fatorEase,
                'ultima_revisao' => $agora,
                'proxima_revisao' => $agora->modify('+'.self::MINUTOS_APOS_ERRO.' minutes'),
            ];
        }

        $repeticoes++;
        $intervalo = match (true) {
            $repeticoes === 1 => 1,
            $repeticoes === 2 => 6,
            default => max(1, (int) round(max(1, $intervalo) * $fatorEase)),
        };

        return [
            'repeticoes' => $repeticoes,
            'intervalo_dias' => $intervalo,
            'fator_ease' => $fatorEase,
            'ultima_revisao' => $agora,
            'proxima_revisao' => $agora->setTime(0, 0)->modify("+{$intervalo} days"),
        ];
    }

    /**
     * EF' = EF + (0.1 − (5 − q) × (0.08 + (5 − q) × 0.02)), com mínimo de 1.3.
     */
    private function novoFatorEase(float $fatorEase, int $nota): float
    {
        $distancia = 5 - $nota;
        $novo = $fatorEase + (0.1 - $distancia * (0.08 + $distancia * 0.02));

        return round(max(self::FATOR_EASE_MINIMO, $novo), 2);
    }
}

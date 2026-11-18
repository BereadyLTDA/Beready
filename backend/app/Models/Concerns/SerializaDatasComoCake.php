<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Datas no JSON no mesmo formato da versão em CakePHP (ISO 8601 com o fuso: 2026-11-15T16:22:53-03:00),
 * em vez do padrão do Laravel (UTC com microssegundos), para o frontend não perceber a troca.
 */
trait SerializaDatasComoCake
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }
}

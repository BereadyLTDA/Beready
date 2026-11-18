<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Contadores em cache (rate limit, uso diário da IA) com prazo de validade.
 */
final class Contador
{
    /**
     * Soma 1 ao contador e devolve o total, já contando com esta chamada.
     *
     * add() só cria a chave (com o prazo) se ela ainda não existir; increment() é atômico no Redis,
     * então chamadas simultâneas, inclusive em instâncias diferentes da API, nunca leem o mesmo valor.
     */
    public static function incrementar(string $chave, int $segundos): int
    {
        Cache::add($chave, 0, $segundos);

        return (int) Cache::increment($chave);
    }
}

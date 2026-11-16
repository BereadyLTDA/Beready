<?php

declare(strict_types=1);

namespace App\Cache;

use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;

/**
 * Contadores em cache (rate limit, uso diário da IA). O prazo de validade é o do config usado.
 */
final class Contador
{
    /**
     * Soma 1 ao contador e devolve o total, já contando com esta chamada.
     *
     * No Redis o incremento é atômico, então chamadas simultâneas — inclusive em instâncias
     * diferentes da API — nunca leem o mesmo valor. O FileEngine não sabe incrementar: fica o
     * ler-e-gravar, que serve só para desenvolvimento com um processo.
     */
    public static function incrementar(string $key, string $config): int
    {
        if (Cache::pool($config) instanceof FileEngine) {
            $total = (int)Cache::read($key, $config) + 1;
            Cache::write($key, $total, $config);

            return $total;
        }

        // add() só cria a chave (com o prazo do config) se ela ainda não existir
        Cache::add($key, 0, $config);

        return (int)Cache::increment($key, 1, $config);
    }
}

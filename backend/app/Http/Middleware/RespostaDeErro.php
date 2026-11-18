<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;

/**
 * Resposta de erro dos middlewares, no mesmo formato dos controllers: { success: false, message }.
 */
final class RespostaDeErro
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function json(int $status, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }
}

<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Prompt;
use App\Models\Quiz;
use App\Models\Tag;

/**
 * Limites de criação do plano (planos.limites, ex. {"flashcards":50,"quizes":10,"prompts":5}).
 *
 * - Recurso sem chave em `limites` = ilimitado.
 * - Valor -1 (ou qualquer negativo) ou >= LIMITE_ILIMITADO = ilimitado (mesma regra do front, usePlan.ts).
 * - Admin não tem limite.
 */
class PlanoLimiteService
{
    public const LIMITE_ILIMITADO = 999999;

    /**
     * Recurso => [model, coluna do dono].
     */
    public const RECURSOS = [
        'flashcards' => [Flashcard::class, 'usuario_id'],
        'quizes' => [Quiz::class, 'usuario_id'],
        'prompts' => [Prompt::class, 'usuario_id'],
        'tags' => [Tag::class, 'criado_por'],
    ];

    public function __construct(
        private AssinaturaService $assinaturaService,
        private PermissionService $permissionService,
    ) {}

    /**
     * Verifica se o usuário ainda pode criar um registro do recurso.
     *
     * @return array{permitido: bool, recurso: string, limite: int|null, usados: int}
     *                                                                                limite null = ilimitado
     */
    public function verificar(int $usuarioId, string $recurso): array
    {
        if (! isset(self::RECURSOS[$recurso])) {
            throw new \InvalidArgumentException("Recurso sem controle de limite: {$recurso}");
        }

        $resultado = ['permitido' => true, 'recurso' => $recurso, 'limite' => null, 'usados' => 0];

        if ($this->permissionService->isAdmin($usuarioId)) {
            return $resultado;
        }

        $limite = $this->getLimite($usuarioId, $recurso);
        if ($limite === null) {
            return $resultado;
        }

        $usados = $this->contar($usuarioId, $recurso);

        return [
            'permitido' => $usados < $limite,
            'recurso' => $recurso,
            'limite' => $limite,
            'usados' => $usados,
        ];
    }

    /**
     * Limite do recurso no plano ativo do usuário; null = ilimitado.
     */
    public function getLimite(int $usuarioId, string $recurso): ?int
    {
        $assinatura = $this->assinaturaService->getAtivaOuGratuita($usuarioId);
        // Sem plano nenhum (nem o Gratuito existe): não há limite configurado para aplicar
        if (! $assinatura || ! $assinatura->plano) {
            return null;
        }

        $limites = $assinatura->plano->limites_array;
        if (! array_key_exists($recurso, $limites) || ! is_numeric($limites[$recurso])) {
            return null;
        }

        $limite = (int) $limites[$recurso];

        return self::isIlimitado($limite) ? null : $limite;
    }

    public function contar(int $usuarioId, string $recurso): int
    {
        [$model, $coluna] = self::RECURSOS[$recurso];

        return $model::where($coluna, $usuarioId)->count();
    }

    public static function isIlimitado(int $limite): bool
    {
        return $limite < 0 || $limite >= self::LIMITE_ILIMITADO;
    }
}

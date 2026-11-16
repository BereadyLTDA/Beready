<?php

declare(strict_types=1);

namespace App\Services;

use Cake\ORM\TableRegistry;

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
     * Recurso => [tabela, coluna do dono].
     */
    public const RECURSOS = [
        'flashcards' => ['Flashcards', 'usuario_id'],
        'quizes' => ['Quizes', 'usuario_id'],
        'prompts' => ['Prompts', 'usuario_id'],
        'tags' => ['Tags', 'criado_por'],
    ];

    public function __construct(
        private AssinaturaService $assinaturaService,
        private PermissionService $permissionService,
    ) {
    }

    /**
     * Verifica se o usuário ainda pode criar um registro do recurso.
     *
     * @return array{permitido: bool, recurso: string, limite: int|null, usados: int}
     *   limite null = ilimitado
     */
    public function verificar(int $usuarioId, string $recurso): array
    {
        if (!isset(self::RECURSOS[$recurso])) {
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
        if (!$assinatura || !$assinatura->plano) {
            return null;
        }

        $limites = $assinatura->plano->limites_array ?? [];
        if (!is_array($limites) || !array_key_exists($recurso, $limites) || !is_numeric($limites[$recurso])) {
            return null;
        }

        $limite = (int)$limites[$recurso];

        return self::isIlimitado($limite) ? null : $limite;
    }

    public function contar(int $usuarioId, string $recurso): int
    {
        [$tabela, $coluna] = self::RECURSOS[$recurso];

        return TableRegistry::getTableLocator()->get($tabela)->find()
            ->where([$coluna => $usuarioId])
            ->count();
    }

    public static function isIlimitado(int $limite): bool
    {
        return $limite < 0 || $limite >= self::LIMITE_ILIMITADO;
    }
}

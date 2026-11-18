<?php

namespace App\Http\Middleware;

use App\Services\PlanoLimiteService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica o limite do plano (planos.limites) a uma rota de criação.
 * Uso em routes/api.php: ->middleware('limite.plano:flashcards').
 */
class LimiteDoPlano
{
    private const NOMES = [
        'flashcards' => 'flashcards',
        'quizes' => 'quizzes',
        'prompts' => 'prompts',
        'tags' => 'tags',
    ];

    public function __construct(private PlanoLimiteService $planoLimite) {}

    public function handle(Request $request, Closure $next, string $recurso): Response
    {
        $userId = (int) $request->attributes->get('user_id');
        // Sem usuário o AutenticaJwt já respondeu 401; aqui só por segurança
        if (! $userId) {
            return $next($request);
        }

        $verificacao = $this->planoLimite->verificar($userId, $recurso);
        if ($verificacao['permitido']) {
            return $next($request);
        }

        $message = sprintf(
            'Você atingiu o limite de %d %s do seu plano. Faça upgrade para criar mais.',
            $verificacao['limite'],
            self::NOMES[$recurso],
        );

        return RespostaDeErro::json(403, $message, [
            'errors' => [
                'limite' => [
                    'recurso' => $recurso,
                    'limite' => $verificacao['limite'],
                    'usados' => $verificacao['usados'],
                ],
            ],
        ]);
    }
}

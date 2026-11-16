<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\PlanoLimiteService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Aplica os limites do plano (planos.limites) nos POST de criação.
 * Deve rodar depois do JwtAuthMiddleware, que define o atributo user_id.
 */
class PlanoLimiteMiddleware implements MiddlewareInterface
{
    use JsonErrorResponseTrait;

    /**
     * Path do POST de criação => recurso em planos.limites.
     */
    private const ROTAS = [
        '/flashcards' => 'flashcards',
        '/quizes' => 'quizes',
        // Gerar a partir dos flashcards também cria um quiz
        '/quizes/gerar' => 'quizes',
        '/prompts' => 'prompts',
        '/tags' => 'tags',
    ];

    private const NOMES = [
        'flashcards' => 'flashcards',
        'quizes' => 'quizzes',
        'prompts' => 'prompts',
        'tags' => 'tags',
    ];

    public function __construct(private PlanoLimiteService $planoLimite)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $path = rtrim($request->getUri()->getPath(), '/');
        $recurso = self::ROTAS[$path] ?? null;
        $userId = (int)$request->getAttribute('user_id');

        // Sem usuário o JwtAuthMiddleware já respondeu 401; aqui só por segurança
        if ($recurso === null || !$userId) {
            return $handler->handle($request);
        }

        $verificacao = $this->planoLimite->verificar($userId, $recurso);
        if ($verificacao['permitido']) {
            return $handler->handle($request);
        }

        $message = sprintf(
            'Você atingiu o limite de %d %s do seu plano. Faça upgrade para criar mais.',
            $verificacao['limite'],
            self::NOMES[$recurso]
        );

        return $this->jsonErrorResponse(403, $message, [
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

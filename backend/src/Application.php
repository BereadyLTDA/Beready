<?php

declare(strict_types=1);

namespace App;

use App\Contracts\CloudinaryServiceInterface;
use App\Contracts\FlashcardRepositoryInterface;
use App\Contracts\FlashcardTagRepositoryInterface;
use App\Contracts\FraseRepositoryInterface;
use App\Contracts\GeradorTextoInterface;
use App\Contracts\ImagemRepositoryInterface;
use App\Contracts\PreferenciaRepositoryInterface;
use App\Contracts\PromptRepositoryInterface;
use App\Contracts\QuizRepositoryInterface;
use App\Contracts\StripeGatewayInterface;
use App\Contracts\TagRepositoryInterface;
use App\Contracts\TraducaoRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Contracts\UserUseCaseInterface;
use App\Exceptions\SentryExceptionRenderer;
use App\Middleware\AdminMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\PlanoLimiteMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\FlashcardRepository;
use App\Repositories\FlashcardTagRepository;
use App\Repositories\FraseRepository;
use App\Repositories\ImagemRepository;
use App\Repositories\PreferenciaRepository;
use App\Repositories\PromptRepository;
use App\Repositories\QuizRepository;
use App\Repositories\TagRepository;
use App\Repositories\TraducaoRepository;
use App\Repositories\UserRepository;
use App\Services\ClaudeGeradorTexto;
use App\Services\CloudinaryService;
use App\Services\QuizGeradorService;
use App\Services\QuizQuestaoService;
use App\Services\StripeGateway;
use App\Services\UserService;
use Cake\Core\ContainerInterface;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
use League\Container\ReflectionContainer;

class Application extends BaseApplication
{
    /**
     * Contrato => implementação usada em produção.
     */
    private const IMPLEMENTACOES = [
        StripeGatewayInterface::class => StripeGateway::class,
        GeradorTextoInterface::class => ClaudeGeradorTexto::class,
        CloudinaryServiceInterface::class => CloudinaryService::class,
        UserUseCaseInterface::class => UserService::class,
        UserRepositoryInterface::class => UserRepository::class,
        FlashcardRepositoryInterface::class => FlashcardRepository::class,
        FlashcardTagRepositoryInterface::class => FlashcardTagRepository::class,
        TagRepositoryInterface::class => TagRepository::class,
        QuizRepositoryInterface::class => QuizRepository::class,
        PromptRepositoryInterface::class => PromptRepository::class,
        TraducaoRepositoryInterface::class => TraducaoRepository::class,
        ImagemRepositoryInterface::class => ImagemRepository::class,
        FraseRepositoryInterface::class => FraseRepository::class,
        PreferenciaRepositoryInterface::class => PreferenciaRepository::class,
    ];

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // CORS primeiro, para que respostas de erro também levem os headers
        $middlewareQueue->add(new CorsMiddleware());

        // Error Handler logo após o CORS, cobrindo todo o resto da fila
        $middlewareQueue->add(new ErrorHandlerMiddleware([
            'exceptionRenderer' => SentryExceptionRenderer::class,
        ]));

        $middlewareQueue->add(new BodyParserMiddleware());

        // Rate Limit: 300 req/min por IP no geral e 10 req/min por IP nas rotas de autenticação
        $middlewareQueue->add(new RateLimitMiddleware(300, 10, 60));

        // JWT (define user_id/role) e, em seguida, a restrição do escopo /admin
        // Passados pelo nome da classe: o container resolve os serviços do construtor
        $middlewareQueue->add(JwtAuthMiddleware::class);
        $middlewareQueue->add(AdminMiddleware::class);

        // Limites do plano (planos.limites) nos POST de criação de flashcards, quizzes, prompts e tags
        $middlewareQueue->add(PlanoLimiteMiddleware::class);

        $middlewareQueue->add(new RoutingMiddleware($this));

        $middlewareQueue->add(new AssetMiddleware());

        return $middlewareQueue;
    }

    public function routes(RouteBuilder $routes): void
    {
        require CONFIG . 'routes.php';
    }

    /**
     * Injeção de dependências. Controllers, middlewares e serviços declaram o que precisam no
     * construtor e o container monta o grafo por reflexão (autowiring); aqui ficam só as
     * decisões que a reflexão não toma sozinha: qual implementação atende cada interface.
     *
     * Nos testes, `mockService()` troca qualquer uma destas entradas por um fake.
     */
    public function services(ContainerInterface $container): void
    {
        // Uma instância de cada serviço por requisição
        $container->delegate(new ReflectionContainer(true));

        foreach (self::IMPLEMENTACOES as $contrato => $implementacao) {
            $container->add($contrato, $implementacao);
        }

        // O terceiro argumento (como embaralhar) é opcional e só interessa aos testes
        $container->add(QuizGeradorService::class)
            ->addArguments([QuizQuestaoService::class, QuizRepositoryInterface::class]);
    }
}

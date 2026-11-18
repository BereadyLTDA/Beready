<?php

namespace App\Providers;

use App\Contracts\CloudinaryServiceInterface;
use App\Contracts\GeradorTextoInterface;
use App\Contracts\StripeGatewayInterface;
use App\Services\ClaudeGeradorTexto;
use App\Services\CloudinaryService;
use App\Services\StripeGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Contrato => implementação usada em produção. Controllers e serviços recebem as dependências
     * pelo construtor e o container resolve o resto sozinho; nos testes, mockService() troca estas.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StripeGatewayInterface::class => StripeGateway::class,
        CloudinaryServiceInterface::class => CloudinaryService::class,
        GeradorTextoInterface::class => ClaudeGeradorTexto::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fora de produção, consulta extra silenciosa (N+1) vira erro. Campos fora do $fillable continuam
        // sendo ignorados em silêncio em todo ambiente, como no CakePHP ($_accessible).
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}

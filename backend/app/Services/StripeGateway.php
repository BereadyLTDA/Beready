<?php

namespace App\Services;

use App\Contracts\StripeGatewayInterface;
use Stripe\StripeClient;

class StripeGateway implements StripeGatewayInterface
{
    private ?StripeClient $client = null;

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('beready.stripe.secret'));
    }

    public function criarCustomer(string $email, string $nome, int $usuarioId): string
    {
        $customer = $this->client()->customers->create([
            'email' => $email,
            'name' => $nome,
            'metadata' => ['user_id' => (string) $usuarioId],
        ]);

        return $customer->id;
    }

    public function criarCheckoutAssinatura(array $params): string
    {
        return (string) $this->client()->checkout->sessions->create($params)->url;
    }

    public function cancelarNoFimDoPeriodo(string $subscriptionId): void
    {
        $this->client()->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
    }

    public function criarSessaoPortal(string $customerId, string $returnUrl): string
    {
        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }
}

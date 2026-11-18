<?php

namespace Tests;

use App\Contracts\StripeGatewayInterface;
use Stripe\Exception\ApiErrorException;

/**
 * StripeGatewayInterface em memória: registra as chamadas e não acessa a rede.
 * Uso: $this->mockService(StripeGatewayInterface::class, fn () => $fake).
 */
class FakeStripeGateway implements StripeGatewayInterface
{
    /** @var array<int, array{string, array}> */
    public array $chamadas = [];

    /**
     * Se definido, todas as chamadas lançam esta exceção.
     */
    public ?ApiErrorException $erro = null;

    private int $sequencia = 0;

    public function criarCustomer(string $email, string $nome, int $usuarioId): string
    {
        $this->registrar('criarCustomer', compact('email', 'nome', 'usuarioId'));

        return 'cus_fake_'.(++$this->sequencia);
    }

    public function criarCheckoutAssinatura(array $params): string
    {
        $this->registrar('criarCheckoutAssinatura', $params);

        return 'https://checkout.stripe.test/c/pay/cs_fake_'.(++$this->sequencia);
    }

    public function cancelarNoFimDoPeriodo(string $subscriptionId): void
    {
        $this->registrar('cancelarNoFimDoPeriodo', compact('subscriptionId'));
    }

    public function criarSessaoPortal(string $customerId, string $returnUrl): string
    {
        $this->registrar('criarSessaoPortal', compact('customerId', 'returnUrl'));

        return 'https://billing.stripe.test/p/session/fake_'.(++$this->sequencia);
    }

    /**
     * Parâmetros das chamadas a um método, na ordem.
     *
     * @return array<int, array>
     */
    public function chamadasDe(string $metodo): array
    {
        return array_values(array_map(
            fn (array $c) => $c[1],
            array_filter($this->chamadas, fn (array $c) => $c[0] === $metodo)
        ));
    }

    private function registrar(string $metodo, array $params): void
    {
        if ($this->erro) {
            throw $this->erro;
        }

        $this->chamadas[] = [$metodo, $params];
    }
}

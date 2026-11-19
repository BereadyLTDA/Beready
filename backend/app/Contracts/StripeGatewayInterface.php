<?php

namespace App\Contracts;

/**
 * Chamadas à API do Stripe usadas pelos planos. Isoladas aqui para que os testes
 * substituam a implementação ($this->mockService() nos testes) sem acessar a rede.
 *
 * As implementações lançam \Stripe\Exception\ApiErrorException em erros da API.
 */
interface StripeGatewayInterface
{
    /**
     * Cria um Customer e devolve o id (cus_...).
     */
    public function criarCustomer(string $email, string $nome, int $usuarioId): string;

    /**
     * Cria uma Checkout Session (mode=subscription) e devolve a URL de pagamento.
     *
     * @param  array  $params  parâmetros de \Stripe\Checkout\Session::create
     */
    public function criarCheckoutAssinatura(array $params): string;

    /**
     * Agenda o cancelamento da subscription no fim do período corrente (cancel_at_period_end=true).
     */
    public function cancelarNoFimDoPeriodo(string $subscriptionId): void;

    /**
     * Cancela a subscription na hora (sem esperar o fim do período). Usado ao excluir a conta e
     * quando uma assinatura nova substitui outra que ainda estava cobrando.
     */
    public function cancelarAgora(string $subscriptionId): void;

    /**
     * Cria uma sessão do Billing Portal e devolve a URL.
     */
    public function criarSessaoPortal(string $customerId, string $returnUrl): string;
}

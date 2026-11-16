<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\AssinaturaService;
use App\Services\PermissionService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Webhooks do Stripe para a assinatura Premium recorrente (checkout criado em PlanosController::assinar).
 *
 * Eventos tratados (todos idempotentes: o Stripe pode reenviar e entregar fora de ordem):
 * - checkout.session.completed     ativa a assinatura e grava o id da Subscription
 * - invoice.paid                   renovação: estende data_fim até o fim do período cobrado
 * - invoice.payment_failed         marca stripe_status=past_due; o acesso continua até data_fim
 * - customer.subscription.updated  sincroniza status e cancelamento agendado (cancel_at_period_end)
 * - customer.subscription.deleted  encerra a assinatura e devolve o usuário ao Gratuito
 */
class PaymentsController extends AppController
{

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private AssinaturaService $assinaturaService,
    ) {
        parent::__construct($request, $permissionService);
    }

    /**
     * POST /payments/webhook (público; a autenticidade vem da assinatura Stripe-Signature)
     */
    public function webhook()
    {
        $this->request->allowMethod(['post']);

        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');
        if (empty($endpointSecret)) {
            Log::error('STRIPE_WEBHOOK_SECRET não configurado');
            return $this->jsonError('Webhook não configurado', 500);
        }

        $payload = (string)$this->request->getBody();
        $sigHeader = $this->request->getHeaderLine('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException | \UnexpectedValueException $e) {
            return $this->jsonError('Assinatura do webhook inválida', 400);
        }

        $objeto = $event->data->object->toArray();

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->checkoutConcluido($objeto);
                    break;
                case 'invoice.paid':
                    $this->faturaPaga($objeto);
                    break;
                case 'invoice.payment_failed':
                    $subscriptionId = $this->subscriptionDaFatura($objeto);
                    if ($subscriptionId) {
                        $this->assinaturaService->registrarFalhaPagamento($subscriptionId);
                    }
                    break;
                case 'customer.subscription.updated':
                    $this->assinaturaService->sincronizarSubscription(
                        (string)$objeto['id'],
                        (string)($objeto['status'] ?? ''),
                        !empty($objeto['cancel_at_period_end']) || !empty($objeto['cancel_at'])
                    );
                    break;
                case 'customer.subscription.deleted':
                    $this->assinaturaService->encerrarSubscription((string)$objeto['id']);
                    break;
            }
        } catch (RecordNotFoundException $e) {
            Log::error("Webhook Stripe {$event->type}: registro não encontrado: " . $e->getMessage());
        } catch (\Exception $e) {
            // Retorna 500 para o Stripe tentar reenviar o evento
            Log::error("Webhook Stripe {$event->type}: " . $e->getMessage());
            return $this->jsonError('Erro ao processar pagamento', 500);
        }

        return $this->jsonSuccess(['received' => true]);
    }

    private function checkoutConcluido(array $session): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $metadata = (array)($session['metadata'] ?? []);
        $userId = (int)($metadata['user_id'] ?? 0);
        $planId = (int)($metadata['plan_id'] ?? 0);
        $ciclo = $metadata['ciclo'] ?? null;

        if (!$userId || !$planId || !in_array($ciclo, AssinaturaService::CICLOS, true)) {
            Log::error('Webhook Stripe com metadata inválida: ' . ($session['id'] ?? '?'));
            return;
        }

        $subscriptionId = $this->idDe($session['subscription'] ?? null);

        // O Stripe pode reenviar o mesmo evento
        if (
            $this->assinaturaService->pagamentoJaProcessado((string)$session['id'])
            || ($subscriptionId && $this->assinaturaService->findPorSubscription($subscriptionId))
        ) {
            return;
        }

        $plano = TableRegistry::getTableLocator()->get('Planos')->get($planId);
        $this->assinaturaService->ativar($userId, $plano, $ciclo, (string)$session['id'], $subscriptionId);

        $this->guardarCustomer($userId, $this->idDe($session['customer'] ?? null));
    }

    private function faturaPaga(array $invoice): void
    {
        $subscriptionId = $this->subscriptionDaFatura($invoice);
        $fimPeriodo = $this->fimDoPeriodoDaFatura($invoice);

        if (!$subscriptionId || !$fimPeriodo) {
            return;
        }

        $assinatura = $this->assinaturaService->registrarRenovacao($subscriptionId, $fimPeriodo);

        // A primeira fatura (subscription_create) pode chegar antes do checkout.session.completed,
        // que é quem cria a assinatura; nas renovações a assinatura já existe.
        if (!$assinatura && ($invoice['billing_reason'] ?? null) !== 'subscription_create') {
            Log::warning("Webhook Stripe invoice.paid: subscription {$subscriptionId} sem assinatura vigente");
        }
    }

    /**
     * Id da Subscription da fatura: `subscription` (API até 2025-02) ou
     * `parent.subscription_details.subscription` (API 2025-03-31.basil em diante).
     */
    private function subscriptionDaFatura(array $invoice): ?string
    {
        return $this->idDe($invoice['subscription'] ?? null)
            ?? $this->idDe($invoice['parent']['subscription_details']['subscription'] ?? null);
    }

    /**
     * Fim do período cobrado: maior lines.data[].period.end. (O period_end da própria fatura de
     * renovação aponta para o início do novo período, não para o fim.)
     */
    private function fimDoPeriodoDaFatura(array $invoice): ?int
    {
        $fins = [];
        foreach ((array)($invoice['lines']['data'] ?? []) as $linha) {
            if (isset($linha['period']['end'])) {
                $fins[] = (int)$linha['period']['end'];
            }
        }

        return $fins ? max($fins) : null;
    }

    private function guardarCustomer(int $userId, ?string $customerId): void
    {
        if (!$customerId) {
            return;
        }

        $usersTable = TableRegistry::getTableLocator()->get('Users');
        $user = $usersTable->find()->where(['id' => $userId])->first();
        if ($user && empty($user->stripe_customer_id)) {
            $user->stripe_customer_id = $customerId;
            $usersTable->save($user);
        }
    }

    /**
     * Campos do Stripe podem vir como id (string) ou objeto expandido.
     */
    private function idDe(mixed $valor): ?string
    {
        if (is_array($valor)) {
            $valor = $valor['id'] ?? null;
        }

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}

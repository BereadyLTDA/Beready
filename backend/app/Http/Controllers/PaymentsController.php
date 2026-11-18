<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Models\User;
use App\Services\AssinaturaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
class PaymentsController extends Controller
{
    public function __construct(private AssinaturaService $assinaturaService) {}

    // POST /payments/webhook (público; a autenticidade vem da assinatura Stripe-Signature)
    public function webhook(Request $request): JsonResponse
    {
        $endpointSecret = config('beready.stripe.webhook_secret');
        if (empty($endpointSecret)) {
            Log::error('STRIPE_WEBHOOK_SECRET não configurado');

            return $this->jsonError('Webhook não configurado', 500);
        }

        try {
            $event = Webhook::constructEvent(
                (string) $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $endpointSecret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
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
                        (string) $objeto['id'],
                        (string) ($objeto['status'] ?? ''),
                        ! empty($objeto['cancel_at_period_end']) || ! empty($objeto['cancel_at']),
                    );
                    break;

                case 'customer.subscription.deleted':
                    $this->assinaturaService->encerrarSubscription((string) $objeto['id']);
                    break;
            }
        } catch (ModelNotFoundException $e) {
            Log::error("Webhook Stripe {$event->type}: registro não encontrado: ".$e->getMessage());
        } catch (\Exception $e) {
            // Retorna 500 para o Stripe tentar reenviar o evento
            Log::error("Webhook Stripe {$event->type}: ".$e->getMessage());

            return $this->jsonError('Erro ao processar pagamento', 500);
        }

        return $this->jsonSuccess(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function checkoutConcluido(array $session): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $metadata = (array) ($session['metadata'] ?? []);
        $userId = (int) ($metadata['user_id'] ?? 0);
        $planId = (int) ($metadata['plan_id'] ?? 0);
        $ciclo = $metadata['ciclo'] ?? null;

        if (! $userId || ! $planId || ! in_array($ciclo, AssinaturaService::CICLOS, true)) {
            Log::error('Webhook Stripe com metadata inválida: '.($session['id'] ?? '?'));

            return;
        }

        $subscriptionId = $this->idDe($session['subscription'] ?? null);

        // O Stripe pode reenviar o mesmo evento
        if (
            $this->assinaturaService->pagamentoJaProcessado((string) $session['id'])
            || ($subscriptionId && $this->assinaturaService->findPorSubscription($subscriptionId))
        ) {
            return;
        }

        $plano = Plano::findOrFail($planId);
        $this->assinaturaService->ativar($userId, $plano, $ciclo, (string) $session['id'], $subscriptionId);

        $this->guardarCustomer($userId, $this->idDe($session['customer'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function faturaPaga(array $invoice): void
    {
        $subscriptionId = $this->subscriptionDaFatura($invoice);
        $fimPeriodo = $this->fimDoPeriodoDaFatura($invoice);
        if (! $subscriptionId || ! $fimPeriodo) {
            return;
        }

        $assinatura = $this->assinaturaService->registrarRenovacao($subscriptionId, $fimPeriodo);

        // A primeira fatura (subscription_create) pode chegar antes do checkout.session.completed,
        // que é quem cria a assinatura; nas renovações a assinatura já existe.
        if (! $assinatura && ($invoice['billing_reason'] ?? null) !== 'subscription_create') {
            Log::warning("Webhook Stripe invoice.paid: subscription {$subscriptionId} sem assinatura vigente");
        }
    }

    /**
     * Id da Subscription de uma fatura (campo antigo `subscription` ou o novo `parent.subscription_details`).
     *
     * @param  array<string, mixed>  $invoice
     */
    private function subscriptionDaFatura(array $invoice): ?string
    {
        return $this->idDe($invoice['subscription'] ?? null)
            ?? $this->idDe($invoice['parent']['subscription_details']['subscription'] ?? null);
    }

    /**
     * Fim do período cobrado: o maior period.end entre as linhas da fatura.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function fimDoPeriodoDaFatura(array $invoice): ?int
    {
        $fins = [];
        foreach ((array) ($invoice['lines']['data'] ?? []) as $linha) {
            if (isset($linha['period']['end'])) {
                $fins[] = (int) $linha['period']['end'];
            }
        }

        return $fins ? max($fins) : null;
    }

    private function guardarCustomer(int $userId, ?string $customerId): void
    {
        if (! $customerId) {
            return;
        }

        $user = User::find($userId);
        if ($user && empty($user->stripe_customer_id)) {
            $user->stripe_customer_id = $customerId;
            $user->save();
        }
    }

    /**
     * Id de um campo do Stripe que pode vir como string ou como objeto expandido.
     */
    private function idDe(mixed $valor): ?string
    {
        if (is_array($valor)) {
            $valor = $valor['id'] ?? null;
        }

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}

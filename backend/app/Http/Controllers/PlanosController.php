<?php

namespace App\Http\Controllers;

use App\Contracts\StripeGatewayInterface;
use App\Models\Plano;
use App\Models\User;
use App\Services\AssinaturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

class PlanosController extends Controller
{
    public function __construct(
        private AssinaturaService $assinaturaService,
        private StripeGatewayInterface $stripe,
    ) {}

    // GET /planos (público)
    public function index(): JsonResponse
    {
        try {
            $planos = Plano::with('role')->where('is_ativo', true)->orderBy('ordem')->get();

            return $this->jsonSuccess($planos);
        } catch (\Exception $e) {
            Log::error('PlanosController::index: '.$e->getMessage());

            return $this->jsonError('Erro ao listar planos', 500);
        }
    }

    // POST /planos/{id}/assinar  { ciclo: mensal|anual }
    public function assinar(Request $request, string $id): JsonResponse
    {
        $userId = $this->currentUserId();
        $ciclo = $request->input('ciclo');

        if (! in_array($ciclo, AssinaturaService::CICLOS, true)) {
            return $this->jsonError('Ciclo de cobrança inválido. Use: '.implode(', ', AssinaturaService::CICLOS), 400);
        }

        $plano = Plano::find((int) $id);
        if (! $plano) {
            return $this->jsonError('Plano não encontrado', 404);
        }

        if (! $plano->is_ativo) {
            return $this->jsonError('Plano indisponível', 400);
        }

        $atual = $this->assinaturaService->getAtiva($userId);
        if ($atual && (int) $atual->plano_id === (int) $plano->id) {
            return $this->jsonError('Você já possui este plano', 409);
        }

        // Trocar de plano encerraria só a assinatura local: a Subscription do Stripe continuaria cobrando
        if ($atual && ! empty($atual->stripe_subscription_id)) {
            return $this->jsonError(
                'Você tem uma assinatura recorrente ativa. Cancele-a ou gerencie o pagamento antes de trocar de plano.',
                409,
            );
        }

        if ($this->assinaturaService->jaUsouTrial($userId, $plano)) {
            return $this->jsonError('Você já utilizou o período de teste deste plano', 409);
        }

        $preco = $this->assinaturaService->getPreco($plano, $ciclo);

        if ($preco <= 0) {
            try {
                $assinatura = $this->assinaturaService->ativar($userId, $plano, $ciclo);
            } catch (\Exception $e) {
                Log::error('ERRO ao ativar plano: '.$e->getMessage());

                return $this->jsonError('Erro ao ativar plano', 500);
            }

            return $this->jsonSuccess([
                'requires_payment' => false,
                'checkout_url' => null,
                'assinatura' => $assinatura,
            ], 'Plano ativado com sucesso!');
        }

        $frontendUrl = config('beready.frontend_url');
        $metadata = [
            'user_id' => (string) $userId,
            'plan_id' => (string) $plano->id,
            'ciclo' => $ciclo,
        ];

        try {
            $customerId = $this->stripeCustomerId($userId);

            // Assinatura recorrente: o Stripe cobra a cada mês/ano e avisa pelos webhooks invoice.*
            $checkoutUrl = $this->stripe->criarCheckoutAssinatura([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'brl',
                        'product_data' => [
                            'name' => sprintf('%s (%s)', $plano->nome, $ciclo),
                        ],
                        'unit_amount' => (int) round($preco * 100),
                        'recurring' => [
                            'interval' => $ciclo === 'anual' ? 'year' : 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => (string) $userId,
                'success_url' => $frontendUrl.'dashboard?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl.'planos?canceled=true',
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ERRO STRIPE: '.$e->getMessage());

            return $this->jsonError('Erro ao gerar pagamento', 502);
        }

        return $this->jsonSuccess([
            'requires_payment' => true,
            'checkout_url' => $checkoutUrl,
        ], 'Redirecionando para o pagamento...');
    }

    /**
     * POST /planos/cancelar
     *
     * Recorrente: agenda o cancelamento no fim do período (acesso mantido até data_fim).
     * Trial/avulsa: encerra na hora e devolve o usuário ao Gratuito.
     */
    public function cancelar(): JsonResponse
    {
        $userId = $this->currentUserId();

        try {
            $assinatura = $this->assinaturaService->cancelar($userId, $this->stripe);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (ApiErrorException $e) {
            Log::error('ERRO STRIPE ao cancelar assinatura: '.$e->getMessage());

            return $this->jsonError('Não foi possível cancelar a assinatura no Stripe. Tente novamente.', 502);
        } catch (\Exception $e) {
            Log::error('ERRO ao cancelar assinatura: '.$e->getMessage());

            return $this->jsonError('Erro ao cancelar assinatura', 500);
        }

        $agendado = (bool) $assinatura->cancelar_no_fim_periodo;
        $ativoAte = $agendado && $assinatura->data_fim ? $assinatura->data_fim->format('c') : null;
        $message = $agendado
            ? sprintf('Cancelamento agendado. Você mantém o acesso até %s.', $assinatura->data_fim->format('d/m/Y'))
            : 'Assinatura cancelada com sucesso';

        return $this->jsonSuccess([
            'assinatura' => $assinatura,
            'cancelamento_agendado' => $agendado,
            'ativo_ate' => $ativoAte,
        ], $message);
    }

    /**
     * POST /planos/portal
     * Sessão do Billing Portal do Stripe (trocar cartão, ver faturas, cancelar/reativar).
     */
    public function portal(): JsonResponse
    {
        $user = User::findOrFail($this->currentUserId());

        if (empty($user->stripe_customer_id)) {
            return $this->jsonError('Você ainda não tem pagamentos no Stripe para gerenciar', 400);
        }

        try {
            $url = $this->stripe->criarSessaoPortal($user->stripe_customer_id, config('beready.frontend_url').'planos');
        } catch (InvalidRequestException $e) {
            // Ex.: portal não configurado no dashboard (Settings > Billing > Customer portal)
            Log::error('ERRO STRIPE portal: '.$e->getMessage());

            return $this->jsonError('O portal de pagamentos está indisponível no momento. Tente novamente mais tarde ou fale com o suporte.', 502);
        } catch (\Exception $e) {
            Log::error('ERRO STRIPE portal: '.$e->getMessage());

            return $this->jsonError('Erro ao abrir o portal de pagamentos', 502);
        }

        return $this->jsonSuccess(['url' => $url], 'Redirecionando para o portal de pagamentos...');
    }

    private function stripeCustomerId(int $userId): string
    {
        $user = User::findOrFail($userId);

        if (empty($user->stripe_customer_id)) {
            // Fora do $fillable: gravado só aqui
            $user->stripe_customer_id = $this->stripe->criarCustomer((string) $user->email, (string) $user->nome, $userId);
            $user->saveOrFail();
        }

        return $user->stripe_customer_id;
    }
}

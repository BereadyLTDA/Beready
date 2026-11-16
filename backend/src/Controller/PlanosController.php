<?php

namespace App\Controller;

use App\Contracts\StripeGatewayInterface;
use App\Services\AssinaturaService;
use App\Services\PermissionService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

class PlanosController extends AppController
{
    private $planosTable;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private AssinaturaService $assinaturaService,
    ) {
        parent::__construct($request, $permissionService);
    }

    public function initialize(): void
    {
        parent::initialize();
        $this->planosTable = TableRegistry::getTableLocator()->get('Planos');
    }

    public function index()
    {
        try {
            $planos = $this->planosTable->find()
                ->contain(['Roles'])
                ->where(['Planos.is_ativo' => true])
                ->orderBy(['Planos.ordem' => 'ASC'])
                ->toArray();

            return $this->jsonSuccess($planos);
        } catch (\Exception $e) {
            Log::error('PlanosController::index: ' . $e->getMessage());
            return $this->jsonError('Erro ao listar planos', 500);
        }
    }

    /**
     * POST /planos/{id}/assinar
     * Planos sem custo são ativados na hora; planos pagos geram um checkout de assinatura
     * recorrente no Stripe e a assinatura é ativada pelo webhook (PaymentsController::webhook).
     */
    public function assinar(StripeGatewayInterface $stripe, $id = null)
    {
        $this->request->allowMethod(['post']);

        $userId = $this->currentUserId();

        $ciclo = $this->request->getData('ciclo');
        if (!in_array($ciclo, AssinaturaService::CICLOS, true)) {
            return $this->jsonError('Ciclo de cobrança inválido. Use: ' . implode(', ', AssinaturaService::CICLOS), 400);
        }

        try {
            $plano = $this->planosTable->get((int)$id);
        } catch (RecordNotFoundException $e) {
            return $this->jsonError('Plano não encontrado', 404);
        }

        if (!$plano->is_ativo) {
            return $this->jsonError('Plano indisponível', 400);
        }

        $atual = $this->assinaturaService->getAtiva($userId);
        if ($atual && (int)$atual->plano_id === (int)$plano->id) {
            return $this->jsonError('Você já possui este plano', 409);
        }

        // Trocar de plano encerraria só a assinatura local: a Subscription do Stripe continuaria cobrando
        if ($atual && !empty($atual->stripe_subscription_id)) {
            return $this->jsonError(
                'Você tem uma assinatura recorrente ativa. Cancele-a ou gerencie o pagamento antes de trocar de plano.',
                409
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
                Log::error('ERRO ao ativar plano: ' . $e->getMessage());
                return $this->jsonError('Erro ao ativar plano', 500);
            }

            return $this->jsonSuccess([
                'requires_payment' => false,
                'checkout_url' => null,
                'assinatura' => $assinatura,
            ], 'Plano ativado com sucesso!');
        }

        $frontendUrl = env('APP_BASE_URL');
        $metadata = [
            'user_id' => (string)$userId,
            'plan_id' => (string)$plano->id,
            'ciclo' => $ciclo,
        ];

        try {
            $customerId = $this->stripeCustomerId($userId, $stripe);

            // Assinatura recorrente: o Stripe cobra a cada mês/ano e avisa pelos webhooks invoice.*
            $checkoutUrl = $stripe->criarCheckoutAssinatura([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'brl',
                        'product_data' => [
                            'name' => sprintf('%s (%s)', $plano->nome, $ciclo),
                        ],
                        'unit_amount' => (int)round($preco * 100),
                        'recurring' => [
                            'interval' => $ciclo === 'anual' ? 'year' : 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => (string)$userId,
                'success_url' => $frontendUrl . 'dashboard?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . 'planos?canceled=true',
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ERRO STRIPE: ' . $e->getMessage());
            return $this->jsonError('Erro ao gerar pagamento', 502);
        }

        return $this->jsonSuccess([
            'requires_payment' => true,
            'checkout_url' => $checkoutUrl,
        ], 'Redirecionando para o pagamento...');
    }

    /**
     * POST /planos/cancelar
     * - Premium recorrente (Stripe): agenda o cancelamento no fim do período; o acesso continua até data_fim.
     * - Trial/pagamento único: encerra na hora e devolve o usuário ao plano gratuito.
     *
     * data.cancelamento_agendado indica qual dos dois aconteceu; data.ativo_ate traz o fim do acesso (ou null).
     */
    public function cancelar(StripeGatewayInterface $stripe)
    {
        $this->request->allowMethod(['post']);

        $userId = $this->currentUserId();

        try {
            $assinatura = $this->assinaturaService->cancelar($userId, $stripe);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (ApiErrorException $e) {
            Log::error('ERRO STRIPE ao cancelar assinatura: ' . $e->getMessage());
            return $this->jsonError('Não foi possível cancelar a assinatura no Stripe. Tente novamente.', 502);
        } catch (\Exception $e) {
            Log::error('ERRO ao cancelar assinatura: ' . $e->getMessage());
            return $this->jsonError('Erro ao cancelar assinatura', 500);
        }

        $agendado = (bool)$assinatura->cancelar_no_fim_periodo;
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
    public function portal(StripeGatewayInterface $stripe)
    {
        $this->request->allowMethod(['post']);

        $userId = $this->currentUserId();
        $user = $this->fetchTable('Users')->get($userId);

        if (empty($user->stripe_customer_id)) {
            return $this->jsonError('Você ainda não tem pagamentos no Stripe para gerenciar', 400);
        }

        try {
            $url = $stripe->criarSessaoPortal($user->stripe_customer_id, env('APP_BASE_URL') . 'planos');
        } catch (InvalidRequestException $e) {
            // Ex.: portal não configurado no dashboard (Settings > Billing > Customer portal)
            Log::error('ERRO STRIPE portal: ' . $e->getMessage());
            return $this->jsonError('O portal de pagamentos está indisponível no momento. Tente novamente mais tarde ou fale com o suporte.', 502);
        } catch (\Exception $e) {
            Log::error('ERRO STRIPE portal: ' . $e->getMessage());
            return $this->jsonError('Erro ao abrir o portal de pagamentos', 502);
        }

        return $this->jsonSuccess(['url' => $url], 'Redirecionando para o portal de pagamentos...');
    }

    /**
     * Customer do Stripe do usuário, criado no primeiro checkout e reutilizado depois.
     */
    private function stripeCustomerId(int $userId, StripeGatewayInterface $stripe): string
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->get($userId);

        if (empty($user->stripe_customer_id)) {
            // Fora do _accessible: gravado só aqui
            $user->stripe_customer_id = $stripe->criarCustomer((string)$user->email, (string)$user->nome, $userId);
            $usersTable->saveOrFail($user);
        }

        return $user->stripe_customer_id;
    }
}

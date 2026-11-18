<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tests\ApiTestCase;

/**
 * POST /payments/webhook com eventos assinados localmente (HMAC), sem chamar a API do Stripe.
 */
class PaymentsWebhookTest extends ApiTestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_beready_phpunit';

    private Model $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('STRIPE_WEBHOOK_SECRET', self::WEBHOOK_SECRET);
        $this->user = $this->createUser();
        $this->withBearer(null);
    }

    public function test_checkout_pago_ativa_o_premium(): void
    {
        $gratuita = $this->insert('Assinaturas', [
            'usuario_id' => $this->user->id,
            'plano_id' => self::PLANO_GRATUITO,
            'status' => 'active',
            'is_ativo' => true,
        ]);

        $this->sendWebhook($this->checkoutCompleted('cs_test_ativa', 'anual'));

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['data']['received']);

        $premium = $this->assinaturasDoPagamento('cs_test_ativa')->firstOrFail();
        $this->assertSame(self::PLANO_PREMIUM, $premium->plano_id);
        $this->assertSame('active', $premium->status);
        $this->assertSame('stripe', $premium->payment_gateway);
        $diasRestantes = (int) round(($premium->data_fim->getTimestamp() - time()) / 86400);
        $this->assertGreaterThanOrEqual(365, $diasRestantes);

        $this->assertSame('canceled', $this->fetch('Assinaturas', $gratuita->id)->status);
    }

    public function test_reenvio_do_mesmo_evento_nao_duplica_assinatura(): void
    {
        $payload = $this->checkoutCompleted('cs_test_idempotente', 'mensal');

        $this->sendWebhook($payload);
        $this->assertResponseOk();
        $primeira = $this->assinaturasDoPagamento('cs_test_idempotente')->firstOrFail();

        $this->sendWebhook($payload);
        $this->assertResponseOk();

        $this->assertSame(1, $this->assinaturasDoPagamento('cs_test_idempotente')->count());
        $this->assertSame(1, $this->tabela('Assinaturas')
            ->where(['usuario_id' => $this->user->id, 'status' => 'active'])
            ->count());
        $this->assertSame('active', $this->fetch('Assinaturas', $primeira->id)->status);
    }

    public function test_assinatura_invalida_retorna400(): void
    {
        $payload = $this->checkoutCompleted('cs_test_forjado', 'mensal');

        // Assinado com outro segredo
        $this->sendWebhook($payload, 'whsec_de_um_atacante');
        $this->assertResponseCode(400);

        // Sem o header Stripe-Signature
        $this->call('POST', '/payments/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseCode(400);

        // Payload alterado depois de assinado
        $assinatura = $this->signature($payload, self::WEBHOOK_SECRET);
        $this->call('POST', '/payments/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $assinatura,
            'CONTENT_TYPE' => 'application/json',
        ], str_replace('cs_test_forjado', 'cs_test_trocado', $payload));
        $this->assertResponseCode(400);

        $this->assertSame(0, $this->tabela('Assinaturas')->count());
    }

    public function test_evento_antigo_fora_da_tolerancia_retorna400(): void
    {
        $payload = $this->checkoutCompleted('cs_test_antigo', 'mensal');

        $this->sendWebhook($payload, self::WEBHOOK_SECRET, time() - 3600);

        $this->assertResponseCode(400);
        $this->assertSame(0, $this->tabela('Assinaturas')->count());
    }

    public function test_checkout_nao_pago_nao_ativa_assinatura(): void
    {
        $this->sendWebhook($this->checkoutCompleted('cs_test_pendente', 'mensal', 'unpaid'));

        $this->assertResponseOk();
        $this->assertSame(0, $this->tabela('Assinaturas')->count());
    }

    public function test_outros_eventos_sao_ignorados(): void
    {
        $payload = (string) json_encode([
            'id' => 'evt_test_outro',
            'object' => 'event',
            'type' => 'payment_intent.created',
            'data' => ['object' => ['id' => 'pi_test', 'object' => 'payment_intent']],
        ]);

        $this->sendWebhook($payload);

        $this->assertResponseOk();
        $this->assertSame(0, $this->tabela('Assinaturas')->count());
    }

    // ---- Assinatura recorrente (mode=subscription) ----

    public function test_checkout_de_assinatura_grava_subscription_e_customer(): void
    {
        $this->sendWebhook($this->checkoutCompleted('cs_test_sub', 'mensal', 'paid', 'sub_test_1', 'cus_test_1'));

        $this->assertResponseOk();
        $premium = $this->assinaturasDoPagamento('cs_test_sub')->firstOrFail();
        $this->assertSame('sub_test_1', $premium->stripe_subscription_id);
        $this->assertSame('active', $premium->stripe_status);
        $this->assertFalse($premium->cancelar_no_fim_periodo);
        $this->assertTrue($premium->recorrente);
        $this->assertSame('cus_test_1', $this->fetch('Users', $this->user->id)->stripe_customer_id);

        // Reenvio não duplica
        $this->sendWebhook($this->checkoutCompleted('cs_test_sub', 'mensal', 'paid', 'sub_test_1', 'cus_test_1'));
        $this->assertResponseOk();
        $this->assertSame(1, $this->tabela('Assinaturas')->where(['stripe_subscription_id' => 'sub_test_1'])->count());
    }

    public function test_invoice_paid_estende_data_fim_ate_o_fim_do_periodo(): void
    {
        $premium = $this->premiumRecorrente('sub_test_renova', '+2 days');
        $fimPeriodo = strtotime('+1 month +2 days');

        $payload = $this->invoiceEvent('invoice.paid', 'in_test_renova', 'sub_test_renova', $fimPeriodo);
        $this->sendWebhook($payload);

        $this->assertResponseOk();
        $renovada = $this->fetch('Assinaturas', $premium->id);
        $this->assertSame($fimPeriodo, $renovada->data_fim->getTimestamp());
        $this->assertSame('active', $renovada->status);
        $this->assertSame('active', $renovada->stripe_status);

        // Idempotente: reenvio não estende de novo; período menor nunca reduz
        $this->sendWebhook($payload);
        $this->sendWebhook($this->invoiceEvent('invoice.paid', 'in_test_velha', 'sub_test_renova', strtotime('+1 day')));
        $this->assertSame($fimPeriodo, $this->fetch('Assinaturas', $premium->id)->data_fim->getTimestamp());
    }

    public function test_invoice_paid_no_formato_novo_da_api(): void
    {
        $premium = $this->premiumRecorrente('sub_test_basil', '+1 day');
        $fimPeriodo = strtotime('+1 year');

        // API 2025-03-31.basil+: a subscription vem em parent.subscription_details
        $this->sendWebhook($this->invoiceEvent('invoice.paid', 'in_test_basil', 'sub_test_basil', $fimPeriodo, true));

        $this->assertResponseOk();
        $this->assertSame($fimPeriodo, $this->fetch('Assinaturas', $premium->id)->data_fim->getTimestamp());
    }

    public function test_invoice_paid_reativa_assinatura_que_expirou_durante_as_retentativas(): void
    {
        $premium = $this->premiumRecorrente('sub_test_volta', '-5 days', ['status' => 'expired', 'is_ativo' => false, 'stripe_status' => 'past_due']);
        $gratuita = $this->insert('Assinaturas', [
            'usuario_id' => $this->user->id,
            'plano_id' => self::PLANO_GRATUITO,
            'status' => 'active',
            'is_ativo' => true,
        ]);

        $this->sendWebhook($this->invoiceEvent('invoice.paid', 'in_test_volta', 'sub_test_volta', strtotime('+25 days')));

        $this->assertResponseOk();
        $reativada = $this->fetch('Assinaturas', $premium->id);
        $this->assertSame('active', $reativada->status);
        $this->assertTrue($reativada->is_ativo);
        $this->assertSame('active', $reativada->stripe_status);
        $this->assertSame('canceled', $this->fetch('Assinaturas', $gratuita->id)->status);
    }

    public function test_invoice_paid_de_subscription_desconhecida_eh_ignorado(): void
    {
        $this->sendWebhook($this->invoiceEvent('invoice.paid', 'in_test_x', 'sub_test_desconhecida', strtotime('+1 month')));

        $this->assertResponseOk();
        $this->assertSame(0, $this->tabela('Assinaturas')->count());
    }

    public function test_falha_de_pagamento_marca_past_due_sem_tirar_o_acesso(): void
    {
        $premium = $this->premiumRecorrente('sub_test_falha', '+3 days');

        $this->sendWebhook($this->invoiceEvent('invoice.payment_failed', 'in_test_falha', 'sub_test_falha', strtotime('+1 month')));

        $this->assertResponseOk();
        $atual = $this->fetch('Assinaturas', $premium->id);
        $this->assertSame('past_due', $atual->stripe_status);
        $this->assertSame('active', $atual->status);
        // data_fim não muda: acesso até o fim do período já pago
        $this->assertSame($premium->data_fim->getTimestamp(), $atual->data_fim->getTimestamp());

        $this->actingAs($this->user);
        $this->get('/user/assinatura');
        $this->assertSame(self::PLANO_PREMIUM, $this->responseJson()['data']['plano_id']);
    }

    public function test_past_due_mantem_o_acesso_na_carencia_e_depois_expira(): void
    {
        $naCarencia = $this->premiumRecorrente('sub_test_pd', '-1 hour', ['stripe_status' => 'past_due']);

        $this->actingAs($this->user);
        $this->get('/user/assinatura');
        $this->assertSame($naCarencia->id, $this->responseJson()['data']['id']);
        $this->assertSame('past_due', $this->responseJson()['data']['stripe_status']);

        $this->tabela('Assinaturas')->whereKey($naCarencia->id)->update(['data_fim' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
        $this->get('/user/assinatura');
        $this->assertSame(self::PLANO_GRATUITO, $this->responseJson()['data']['plano_id']);
        $this->assertSame('expired', $this->fetch('Assinaturas', $naCarencia->id)->status);
    }

    public function test_renovacao_em_andamento_tem_carencia(): void
    {
        // Período acabou há 1h, mas o Stripe ainda vai cobrar: continua Premium
        $premium = $this->premiumRecorrente('sub_test_carencia', '-1 hour');

        $this->actingAs($this->user);
        $this->get('/user/assinatura');

        $this->assertSame($premium->id, $this->responseJson()['data']['id']);
        $this->assertTrue($this->responseJson()['data']['recorrente']);
        $this->assertArrayNotHasKey('stripe_subscription_id', $this->responseJson()['data']);
    }

    public function test_subscription_updated_sincroniza_cancelamento_agendado(): void
    {
        $premium = $this->premiumRecorrente('sub_test_upd', '+10 days');

        $this->sendWebhook($this->subscriptionEvent('customer.subscription.updated', 'sub_test_upd', 'active', true));
        $this->assertResponseOk();
        $atual = $this->fetch('Assinaturas', $premium->id);
        $this->assertTrue($atual->cancelar_no_fim_periodo);
        $this->assertSame('active', $atual->status);

        // Reativado pelo portal
        $this->sendWebhook($this->subscriptionEvent('customer.subscription.updated', 'sub_test_upd', 'past_due', false));
        $atual = $this->fetch('Assinaturas', $premium->id);
        $this->assertFalse($atual->cancelar_no_fim_periodo);
        $this->assertSame('past_due', $atual->stripe_status);
    }

    public function test_subscription_updated_com_status_cancelado_encerra(): void
    {
        $premium = $this->premiumRecorrente('sub_test_upd_cancel', '+10 days');

        $this->sendWebhook($this->subscriptionEvent('customer.subscription.updated', 'sub_test_upd_cancel', 'canceled', false));

        $this->assertResponseOk();
        $this->assertSame('canceled', $this->fetch('Assinaturas', $premium->id)->status);
        $this->assertSame(self::PLANO_GRATUITO, $this->ativaDoUsuario()->plano_id);
    }

    public function test_subscription_deleted_encerra_e_volta_ao_gratuito(): void
    {
        $premium = $this->premiumRecorrente('sub_test_del', '+1 day', ['cancelar_no_fim_periodo' => true]);
        $payload = $this->subscriptionEvent('customer.subscription.deleted', 'sub_test_del', 'canceled', false);

        $this->sendWebhook($payload);

        $this->assertResponseOk();
        $encerrada = $this->fetch('Assinaturas', $premium->id);
        $this->assertSame('canceled', $encerrada->status);
        $this->assertFalse($encerrada->is_ativo);
        $this->assertSame('canceled', $encerrada->stripe_status);
        $this->assertNotNull($encerrada->data_cancelamento);
        $this->assertSame(self::PLANO_GRATUITO, $this->ativaDoUsuario()->plano_id);

        // Reenvio não cria outro Gratuito
        $this->sendWebhook($payload);
        $this->assertResponseOk();
        $this->assertSame(1, $this->tabela('Assinaturas')
            ->where(['usuario_id' => $this->user->id, 'status' => 'active'])->count());
    }

    public function test_eventos_de_subscription_com_assinatura_invalida_retornam400(): void
    {
        $premium = $this->premiumRecorrente('sub_test_forjada', '+10 days');

        $this->sendWebhook($this->subscriptionEvent('customer.subscription.deleted', 'sub_test_forjada', 'canceled', false), 'whsec_de_um_atacante');

        $this->assertResponseCode(400);
        $this->assertSame('active', $this->fetch('Assinaturas', $premium->id)->status);
    }

    private function premiumRecorrente(string $subscriptionId, string $dataFim, array $data = []): Model
    {
        return $this->insert('Assinaturas', $data + [
            'usuario_id' => $this->user->id,
            'plano_id' => self::PLANO_PREMIUM,
            'status' => 'active',
            'is_ativo' => true,
            'data_inicio' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'data_fim' => date('Y-m-d H:i:s', strtotime($dataFim)),
            'payment_id' => 'cs_'.$subscriptionId,
            'payment_gateway' => 'stripe',
            'stripe_subscription_id' => $subscriptionId,
            'stripe_status' => 'active',
            'cancelar_no_fim_periodo' => false,
        ]);
    }

    private function ativaDoUsuario(): Model
    {
        return $this->tabela('Assinaturas')
            ->where(['usuario_id' => $this->user->id, 'status' => 'active'])
            ->firstOrFail();
    }

    private function invoiceEvent(string $type, string $invoiceId, string $subscriptionId, int $fimPeriodo, bool $formatoNovo = false): string
    {
        $invoice = [
            'id' => $invoiceId,
            'object' => 'invoice',
            'billing_reason' => 'subscription_cycle',
            'customer' => 'cus_test',
            'lines' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'il_'.$invoiceId,
                    'object' => 'line_item',
                    'period' => ['start' => $fimPeriodo - 30 * 86400, 'end' => $fimPeriodo],
                ]],
            ],
        ];
        if ($formatoNovo) {
            $invoice['parent'] = [
                'type' => 'subscription_details',
                'subscription_details' => ['subscription' => $subscriptionId, 'metadata' => []],
            ];
        } else {
            $invoice['subscription'] = $subscriptionId;
        }

        return (string) json_encode([
            'id' => 'evt_'.$invoiceId.'_'.$type,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $invoice],
        ]);
    }

    private function subscriptionEvent(string $type, string $subscriptionId, string $status, bool $cancelAtPeriodEnd): string
    {
        return (string) json_encode([
            'id' => 'evt_'.$subscriptionId.'_'.$type.'_'.$status,
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'object' => 'subscription',
                    'status' => $status,
                    'cancel_at_period_end' => $cancelAtPeriodEnd,
                    'cancel_at' => null,
                    'customer' => 'cus_test',
                ],
            ],
        ]);
    }

    private function checkoutCompleted(
        string $sessionId,
        string $ciclo,
        string $paymentStatus = 'paid',
        ?string $subscriptionId = null,
        ?string $customerId = null
    ): string {
        $session = [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'client_reference_id' => (string) $this->user->id,
            'metadata' => [
                'user_id' => (string) $this->user->id,
                'plan_id' => (string) self::PLANO_PREMIUM,
                'ciclo' => $ciclo,
            ],
        ];
        if ($subscriptionId) {
            $session += ['mode' => 'subscription', 'subscription' => $subscriptionId, 'customer' => $customerId];
        }

        return (string) json_encode([
            'id' => 'evt_'.$sessionId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ]);
    }

    private function sendWebhook(string $payload, string $secret = self::WEBHOOK_SECRET, ?int $timestamp = null): void
    {
        // Corpo bruto: a assinatura do Stripe é calculada sobre o texto exato do payload
        $this->call('POST', '/payments/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signature($payload, $secret, $timestamp),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    /**
     * Mesmo formato do header Stripe-Signature: t=<timestamp>,v1=<hmac_sha256("<t>.<payload>", secret)>.
     */
    private function signature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$payload}", $secret));
    }

    private function assinaturasDoPagamento(string $paymentId): Builder
    {
        return $this->tabela('Assinaturas')->where(['payment_id' => $paymentId]);
    }
}

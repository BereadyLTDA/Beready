<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\StripeGatewayInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

class AssinaturaService
{
    public const CICLOS = ['mensal', 'anual'];

    /**
     * Carência após data_fim enquanto a renovação automática do Stripe é processada (2 dias).
     */
    public const CARENCIA_RENOVACAO_SEGUNDOS = 2 * 86400;

    private $assinaturasTable;
    private $planosTable;

    public function __construct()
    {
        $this->assinaturasTable = TableRegistry::getTableLocator()->get('Assinaturas');
        $this->planosTable = TableRegistry::getTableLocator()->get('Planos');
    }

    /**
     * Retorna a assinatura ativa do usuário. Se ela já passou da data_fim, é marcada como expirada.
     */
    public function getAtiva(int $usuarioId): ?EntityInterface
    {
        $assinatura = $this->assinaturasTable->find()
            ->contain(['Planos'])
            ->where([
                'Assinaturas.usuario_id' => $usuarioId,
                'Assinaturas.status' => 'active',
            ])
            ->orderBy(['Assinaturas.id' => 'DESC'])
            ->first();

        if ($assinatura && $assinatura->data_fim && $this->vigenteAte($assinatura) < time()) {
            $assinatura->status = 'expired';
            $assinatura->is_ativo = false;
            $this->assinaturasTable->saveOrFail($assinatura);
            return null;
        }

        return $assinatura;
    }

    /**
     * Timestamp até quando a assinatura dá acesso.
     *
     * Assinatura recorrente que vai renovar (stripe_status active ou past_due, sem cancelamento agendado)
     * ganha uma carência além de data_fim: o Stripe só cobra a renovação ~1h depois do fim do período, o
     * invoice.paid pode atrasar e, se a cobrança falhar (past_due), o usuário ainda vê o aviso e o botão do
     * portal para trocar o cartão. Passada a carência sem pagamento, expira (vai para o Gratuito); se o
     * Stripe cobrar depois, o invoice.paid reativa. Cancelamento agendado: o acesso vai só até data_fim.
     */
    public function vigenteAte(EntityInterface $assinatura): int
    {
        $fim = $assinatura->data_fim->getTimestamp();

        $renovacaoEmAndamento = !empty($assinatura->stripe_subscription_id)
            && in_array($assinatura->stripe_status, ['active', 'past_due'], true)
            && !$assinatura->cancelar_no_fim_periodo;

        return $renovacaoEmAndamento ? $fim + self::CARENCIA_RENOVACAO_SEGUNDOS : $fim;
    }

    /**
     * Retorna a assinatura ativa; se não houver, coloca o usuário no plano gratuito.
     */
    public function getAtivaOuGratuita(int $usuarioId): ?EntityInterface
    {
        return $this->getAtiva($usuarioId) ?? $this->ativarPlanoGratuito($usuarioId);
    }

    public function getPreco(EntityInterface $plano, string $ciclo): float
    {
        return (float)($ciclo === 'anual' ? $plano->preco_anual : $plano->preco_mensal);
    }

    public function isGratuito(EntityInterface $plano): bool
    {
        return (float)$plano->preco_mensal <= 0 && (float)$plano->preco_anual <= 0 && (int)$plano->dias_trial === 0;
    }

    /**
     * Trial só pode ser usado uma vez por usuário.
     */
    public function jaUsouTrial(int $usuarioId, EntityInterface $plano): bool
    {
        if ((int)$plano->dias_trial === 0) {
            return false;
        }

        return $this->assinaturasTable->exists([
            'usuario_id' => $usuarioId,
            'plano_id' => $plano->id,
        ]);
    }

    public function pagamentoJaProcessado(string $paymentId): bool
    {
        return $this->assinaturasTable->exists(['payment_id' => $paymentId]);
    }

    /**
     * Assinatura (a mais recente) ligada a uma Subscription do Stripe.
     */
    public function findPorSubscription(string $subscriptionId): ?EntityInterface
    {
        return $this->assinaturasTable->find()
            ->contain(['Planos'])
            ->where(['Assinaturas.stripe_subscription_id' => $subscriptionId])
            ->orderBy(['Assinaturas.id' => 'DESC'])
            ->first();
    }

    /**
     * Ativa o plano para o usuário, encerrando qualquer assinatura ativa anterior.
     *
     * @param string|null $stripeSubscriptionId Subscription do Stripe que renova esta assinatura
     */
    public function ativar(
        int $usuarioId,
        EntityInterface $plano,
        string $ciclo,
        ?string $paymentId = null,
        ?string $stripeSubscriptionId = null
    ): EntityInterface {
        return $this->assinaturasTable->getConnection()->transactional(function () use ($usuarioId, $plano, $ciclo, $paymentId, $stripeSubscriptionId) {
            $this->encerrarAtivas($usuarioId);

            $assinatura = $this->assinaturasTable->newEntity([
                'usuario_id' => $usuarioId,
                'plano_id' => $plano->id,
                'status' => 'active',
                'is_ativo' => true,
                'data_inicio' => date('Y-m-d H:i:s'),
                'data_fim' => $this->calcularDataFim($plano, $ciclo),
                'payment_id' => $paymentId,
                'payment_gateway' => $paymentId ? 'stripe' : null,
            ]);
            // Campos do Stripe fora do _accessible: gravados só aqui
            $assinatura->stripe_subscription_id = $stripeSubscriptionId;
            $assinatura->stripe_status = $stripeSubscriptionId ? 'active' : null;
            $assinatura->cancelar_no_fim_periodo = false;

            $this->assinaturasTable->saveOrFail($assinatura);
            $assinatura->plano = $plano;

            return $assinatura;
        });
    }

    /**
     * Atribui o plano gratuito, caso o usuário ainda não tenha nenhuma assinatura ativa.
     */
    public function ativarPlanoGratuito(int $usuarioId): ?EntityInterface
    {
        $atual = $this->getAtiva($usuarioId);
        if ($atual) {
            return $atual;
        }

        $planoGratuito = $this->planosTable->find()
            ->where(['LOWER(nome)' => 'gratuito'])
            ->first();

        if (!$planoGratuito) {
            error_log("Plano 'Gratuito' não encontrado; usuário {$usuarioId} ficou sem assinatura.");
            return null;
        }

        return $this->ativar($usuarioId, $planoGratuito, 'mensal');
    }

    /**
     * Cancela a assinatura ativa.
     *
     * - Assinatura recorrente do Stripe: agenda o cancelamento no fim do período (cancel_at_period_end)
     *   e mantém o acesso até data_fim; o webhook customer.subscription.deleted devolve ao Gratuito.
     * - Demais (trial, pagamento único antigo): encerra na hora e devolve o usuário ao plano gratuito.
     *
     * @throws \DomainException quando não há assinatura cancelável ou o cancelamento já está agendado
     * @throws \Stripe\Exception\ApiErrorException quando o Stripe recusa o cancelamento
     */
    public function cancelar(int $usuarioId, StripeGatewayInterface $stripe): EntityInterface
    {
        $atual = $this->getAtiva($usuarioId);

        if (!$atual || $this->isGratuito($atual->plano)) {
            throw new \DomainException('Nenhuma assinatura paga ativa para cancelar');
        }

        if (!empty($atual->stripe_subscription_id)) {
            if ($atual->cancelar_no_fim_periodo) {
                throw new \DomainException('O cancelamento desta assinatura já está agendado');
            }

            $stripe->cancelarNoFimDoPeriodo($atual->stripe_subscription_id);

            $atual->cancelar_no_fim_periodo = true;
            $this->assinaturasTable->saveOrFail($atual);

            return $atual;
        }

        return $this->assinaturasTable->getConnection()->transactional(function () use ($usuarioId) {
            $this->encerrarAtivas($usuarioId);

            return $this->ativarPlanoGratuito($usuarioId);
        });
    }

    /**
     * invoice.paid: renovação paga. Estende data_fim até o fim do período cobrado (nunca reduz).
     * Se a assinatura expirou enquanto o Stripe retentava a cobrança, é reativada.
     * Idempotente: reprocessar o mesmo evento não muda nada.
     *
     * @return \Cake\Datasource\EntityInterface|null null quando a subscription ainda não é conhecida
     *   (o checkout.session.completed cria a assinatura) ou já foi encerrada
     */
    public function registrarRenovacao(string $subscriptionId, int $fimPeriodo): ?EntityInterface
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (!$assinatura || $assinatura->status === 'canceled') {
            return null;
        }

        return $this->assinaturasTable->getConnection()->transactional(function () use ($assinatura, $fimPeriodo) {
            if ($assinatura->status !== 'active') {
                // Expirou durante as retentativas: volta a ser a assinatura vigente
                $this->encerrarAtivas((int)$assinatura->usuario_id);
                $assinatura->status = 'active';
                $assinatura->is_ativo = true;
                $assinatura->data_cancelamento = null;
            }

            $fimAtual = $assinatura->data_fim ? $assinatura->data_fim->getTimestamp() : 0;
            if ($fimPeriodo > $fimAtual) {
                $assinatura->data_fim = date('Y-m-d H:i:s', $fimPeriodo);
            }
            $assinatura->stripe_status = 'active';

            return $this->assinaturasTable->saveOrFail($assinatura);
        });
    }

    /**
     * invoice.payment_failed: marca a cobrança como pendente (past_due). O acesso NÃO é retirado na hora:
     * continua até data_fim (fim do período já pago) + a carência de vigenteAte(). Se o Stripe conseguir
     * cobrar, invoice.paid estende (ou reativa); se desistir, customer.subscription.deleted encerra.
     */
    public function registrarFalhaPagamento(string $subscriptionId): ?EntityInterface
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (!$assinatura) {
            return null;
        }

        $assinatura->stripe_status = 'past_due';

        return $this->assinaturasTable->saveOrFail($assinatura);
    }

    /**
     * customer.subscription.updated: sincroniza status e cancelamento agendado.
     * Status terminais (canceled, incomplete_expired) encerram como em customer.subscription.deleted.
     */
    public function sincronizarSubscription(string $subscriptionId, string $stripeStatus, bool $cancelarNoFim): ?EntityInterface
    {
        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            return $this->encerrarSubscription($subscriptionId);
        }

        $assinatura = $this->findPorSubscription($subscriptionId);
        if (!$assinatura) {
            return null;
        }

        $assinatura->stripe_status = $stripeStatus;
        $assinatura->cancelar_no_fim_periodo = $cancelarNoFim;

        return $this->assinaturasTable->saveOrFail($assinatura);
    }

    /**
     * customer.subscription.deleted: encerra a assinatura e devolve o usuário ao plano gratuito.
     */
    public function encerrarSubscription(string $subscriptionId): ?EntityInterface
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (!$assinatura) {
            return null;
        }

        return $this->assinaturasTable->getConnection()->transactional(function () use ($assinatura) {
            $estavaAtiva = $assinatura->status === 'active';

            $assinatura->stripe_status = 'canceled';
            $assinatura->cancelar_no_fim_periodo = false;
            if ($estavaAtiva) {
                $assinatura->status = 'canceled';
                $assinatura->is_ativo = false;
                $assinatura->data_cancelamento = date('Y-m-d H:i:s');
            }
            $this->assinaturasTable->saveOrFail($assinatura);

            if ($estavaAtiva) {
                $this->ativarPlanoGratuito((int)$assinatura->usuario_id);
            }

            return $assinatura;
        });
    }

    private function encerrarAtivas(int $usuarioId): void
    {
        $this->assinaturasTable->updateAll(
            [
                'status' => 'canceled',
                'is_ativo' => false,
                'data_cancelamento' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            ['usuario_id' => $usuarioId, 'status' => 'active']
        );
    }

    private function calcularDataFim(EntityInterface $plano, string $ciclo): ?string
    {
        if ($this->getPreco($plano, $ciclo) > 0) {
            return date('Y-m-d H:i:s', strtotime($ciclo === 'anual' ? '+1 year' : '+1 month'));
        }

        // Plano sem custo: expira no fim do trial, ou nunca (gratuito)
        $diasTrial = (int)$plano->dias_trial;

        return $diasTrial > 0 ? date('Y-m-d H:i:s', strtotime("+{$diasTrial} days")) : null;
    }
}

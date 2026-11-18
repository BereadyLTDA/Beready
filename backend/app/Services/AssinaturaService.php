<?php

namespace App\Services;

use App\Contracts\StripeGatewayInterface;
use App\Models\Assinatura;
use App\Models\Plano;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class AssinaturaService
{
    public const CICLOS = ['mensal', 'anual'];

    /**
     * Carência após data_fim enquanto a renovação automática do Stripe é processada (2 dias).
     */
    public const CARENCIA_RENOVACAO_SEGUNDOS = 2 * 86400;

    /**
     * Retorna a assinatura ativa do usuário. Se ela já passou da data_fim, é marcada como expirada.
     */
    public function getAtiva(int $usuarioId): ?Assinatura
    {
        $assinatura = Assinatura::with('plano')
            ->where('usuario_id', $usuarioId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($assinatura && $assinatura->data_fim && $this->vigenteAte($assinatura) < now()->getTimestamp()) {
            $assinatura->status = 'expired';
            $assinatura->is_ativo = false;
            $assinatura->saveOrFail();

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
    public function vigenteAte(Assinatura $assinatura): int
    {
        $fim = $assinatura->data_fim->getTimestamp();

        $renovacaoEmAndamento = ! empty($assinatura->stripe_subscription_id)
            && in_array($assinatura->stripe_status, ['active', 'past_due'], true)
            && ! $assinatura->cancelar_no_fim_periodo;

        return $renovacaoEmAndamento ? $fim + self::CARENCIA_RENOVACAO_SEGUNDOS : $fim;
    }

    /**
     * Retorna a assinatura ativa; se não houver, coloca o usuário no plano gratuito.
     */
    public function getAtivaOuGratuita(int $usuarioId): ?Assinatura
    {
        return $this->getAtiva($usuarioId) ?? $this->ativarPlanoGratuito($usuarioId);
    }

    public function getPreco(Plano $plano, string $ciclo): float
    {
        return (float) ($ciclo === 'anual' ? $plano->preco_anual : $plano->preco_mensal);
    }

    public function isGratuito(Plano $plano): bool
    {
        return (float) $plano->preco_mensal <= 0 && (float) $plano->preco_anual <= 0 && (int) $plano->dias_trial === 0;
    }

    /**
     * Trial só pode ser usado uma vez por usuário.
     */
    public function jaUsouTrial(int $usuarioId, Plano $plano): bool
    {
        if ((int) $plano->dias_trial === 0) {
            return false;
        }

        return Assinatura::where('usuario_id', $usuarioId)->where('plano_id', $plano->id)->exists();
    }

    public function pagamentoJaProcessado(string $paymentId): bool
    {
        return Assinatura::where('payment_id', $paymentId)->exists();
    }

    /**
     * Assinatura (a mais recente) ligada a uma Subscription do Stripe.
     */
    public function findPorSubscription(string $subscriptionId): ?Assinatura
    {
        return Assinatura::with('plano')
            ->where('stripe_subscription_id', $subscriptionId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Ativa o plano para o usuário, encerrando qualquer assinatura ativa anterior.
     *
     * @param  string|null  $stripeSubscriptionId  Subscription do Stripe que renova esta assinatura
     */
    public function ativar(
        int $usuarioId,
        Plano $plano,
        string $ciclo,
        ?string $paymentId = null,
        ?string $stripeSubscriptionId = null,
    ): Assinatura {
        return DB::transaction(function () use ($usuarioId, $plano, $ciclo, $paymentId, $stripeSubscriptionId) {
            $this->encerrarAtivas($usuarioId);

            $assinatura = new Assinatura([
                'usuario_id' => $usuarioId,
                'plano_id' => $plano->id,
                'status' => 'active',
                'is_ativo' => true,
                'data_inicio' => now(),
                'data_fim' => $this->calcularDataFim($plano, $ciclo),
                'payment_id' => $paymentId,
                'payment_gateway' => $paymentId ? 'stripe' : null,
            ]);
            // Campos do Stripe fora do $fillable: gravados só aqui
            $assinatura->stripe_subscription_id = $stripeSubscriptionId;
            $assinatura->stripe_status = $stripeSubscriptionId ? 'active' : null;
            $assinatura->cancelar_no_fim_periodo = false;

            $assinatura->saveOrFail();
            $assinatura->setRelation('plano', $plano);

            return $assinatura;
        });
    }

    /**
     * Atribui o plano gratuito, caso o usuário ainda não tenha nenhuma assinatura ativa.
     */
    public function ativarPlanoGratuito(int $usuarioId): ?Assinatura
    {
        $atual = $this->getAtiva($usuarioId);
        if ($atual) {
            return $atual;
        }

        $planoGratuito = Plano::whereRaw('LOWER(nome) = ?', ['gratuito'])->first();

        if (! $planoGratuito) {
            Log::error("Plano 'Gratuito' não encontrado; usuário {$usuarioId} ficou sem assinatura.");

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
     * @throws ApiErrorException quando o Stripe recusa o cancelamento
     */
    public function cancelar(int $usuarioId, StripeGatewayInterface $stripe): Assinatura
    {
        $atual = $this->getAtiva($usuarioId);

        if (! $atual || $this->isGratuito($atual->plano)) {
            throw new \DomainException('Nenhuma assinatura paga ativa para cancelar');
        }

        if (! empty($atual->stripe_subscription_id)) {
            if ($atual->cancelar_no_fim_periodo) {
                throw new \DomainException('O cancelamento desta assinatura já está agendado');
            }

            $stripe->cancelarNoFimDoPeriodo($atual->stripe_subscription_id);

            $atual->cancelar_no_fim_periodo = true;
            $atual->saveOrFail();

            return $atual;
        }

        return DB::transaction(function () use ($usuarioId) {
            $this->encerrarAtivas($usuarioId);

            return $this->ativarPlanoGratuito($usuarioId);
        });
    }

    /**
     * invoice.paid: renovação paga. Estende data_fim até o fim do período cobrado (nunca reduz).
     * Se a assinatura expirou enquanto o Stripe retentava a cobrança, é reativada.
     * Idempotente: reprocessar o mesmo evento não muda nada.
     *
     * @return Assinatura|null null quando a subscription ainda não é conhecida (o
     *                         checkout.session.completed cria a assinatura) ou já foi encerrada
     */
    public function registrarRenovacao(string $subscriptionId, int $fimPeriodo): ?Assinatura
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (! $assinatura || $assinatura->status === 'canceled') {
            return null;
        }

        return DB::transaction(function () use ($assinatura, $fimPeriodo) {
            if ($assinatura->status !== 'active') {
                // Expirou durante as retentativas: volta a ser a assinatura vigente
                $this->encerrarAtivas((int) $assinatura->usuario_id);
                $assinatura->status = 'active';
                $assinatura->is_ativo = true;
                $assinatura->data_cancelamento = null;
            }

            $fimAtual = $assinatura->data_fim ? $assinatura->data_fim->getTimestamp() : 0;
            if ($fimPeriodo > $fimAtual) {
                $assinatura->data_fim = Carbon::createFromTimestamp($fimPeriodo, config('app.timezone'));
            }
            $assinatura->stripe_status = 'active';
            $assinatura->saveOrFail();

            return $assinatura;
        });
    }

    /**
     * invoice.payment_failed: marca a cobrança como pendente (past_due). O acesso NÃO é retirado na hora:
     * continua até data_fim (fim do período já pago) + a carência de vigenteAte(). Se o Stripe conseguir
     * cobrar, invoice.paid estende (ou reativa); se desistir, customer.subscription.deleted encerra.
     */
    public function registrarFalhaPagamento(string $subscriptionId): ?Assinatura
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (! $assinatura) {
            return null;
        }

        $assinatura->stripe_status = 'past_due';
        $assinatura->saveOrFail();

        return $assinatura;
    }

    /**
     * customer.subscription.updated: sincroniza status e cancelamento agendado.
     * Status terminais (canceled, incomplete_expired) encerram como em customer.subscription.deleted.
     */
    public function sincronizarSubscription(string $subscriptionId, string $stripeStatus, bool $cancelarNoFim): ?Assinatura
    {
        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            return $this->encerrarSubscription($subscriptionId);
        }

        $assinatura = $this->findPorSubscription($subscriptionId);
        if (! $assinatura) {
            return null;
        }

        $assinatura->stripe_status = $stripeStatus;
        $assinatura->cancelar_no_fim_periodo = $cancelarNoFim;
        $assinatura->saveOrFail();

        return $assinatura;
    }

    /**
     * customer.subscription.deleted: encerra a assinatura e devolve o usuário ao plano gratuito.
     */
    public function encerrarSubscription(string $subscriptionId): ?Assinatura
    {
        $assinatura = $this->findPorSubscription($subscriptionId);
        if (! $assinatura) {
            return null;
        }

        return DB::transaction(function () use ($assinatura) {
            $estavaAtiva = $assinatura->status === 'active';

            $assinatura->stripe_status = 'canceled';
            $assinatura->cancelar_no_fim_periodo = false;
            if ($estavaAtiva) {
                $assinatura->status = 'canceled';
                $assinatura->is_ativo = false;
                $assinatura->data_cancelamento = now();
            }
            $assinatura->saveOrFail();

            if ($estavaAtiva) {
                $this->ativarPlanoGratuito((int) $assinatura->usuario_id);
            }

            return $assinatura;
        });
    }

    private function encerrarAtivas(int $usuarioId): void
    {
        Assinatura::where('usuario_id', $usuarioId)
            ->where('status', 'active')
            ->update([
                'status' => 'canceled',
                'is_ativo' => false,
                'data_cancelamento' => now(),
                'updated_at' => now(),
            ]);
    }

    private function calcularDataFim(Plano $plano, string $ciclo): ?Carbon
    {
        if ($this->getPreco($plano, $ciclo) > 0) {
            return $ciclo === 'anual' ? now()->addYear() : now()->addMonth();
        }

        // Plano sem custo: expira no fim do trial, ou nunca (gratuito)
        $diasTrial = (int) $plano->dias_trial;

        return $diasTrial > 0 ? now()->addDays($diasTrial) : null;
    }
}

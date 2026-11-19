<!-- frontend/src/views/PlanosPage.vue -->

<template>
  <!-- Página pública: logado, mostra o navbar como as outras telas; sem sessão, volta para o login -->
  <AppNavbar v-if="isAuthenticated" />
  <PageContainer as="main" class="planos min-h-screen">
    <PageHeader
      :title="$t('planos.title')"
      :subtitle="$t('planos.subtitle')"
      icon="star"
      :back-to="isAuthenticated ? '/dashboard' : '/login'"
    />

    <BaseSpinner v-if="isLoadingPlanos" center size="lg" />
    <EmptyState v-else-if="planosData.length === 0" :title="$t('planos.noPlans')" icon="star" />

    <ul v-else class="planos__grid grid list-none grid-cols-fit-70 items-stretch gap-6">
      <li v-for="plano in planosData" :key="plano.id" class="planos__item flex min-w-0">
        <BaseCard
          as="article"
          class="planos__card flex-1 text-center"
          :highlight="isPlanoAtual(plano) ? 'success' : plano.preco_mensal > 0 ? 'primary' : undefined"
        >
          <div class="planos__card-body flex h-full flex-col items-center gap-3">
            <div class="planos__badges flex min-h-7 justify-center">
              <BaseBadge v-if="isPlanoAtual(plano)" variant="success" solid icon>
                {{ $t('planos.currentPlan') }}
              </BaseBadge>
              <BaseBadge v-else-if="plano.preco_mensal > 0" variant="primary" solid icon="star">
                {{ $t('planos.mostPopular') }}
              </BaseBadge>
            </div>

            <header class="planos__card-header">
              <h2 class="planos__name wrap-anywhere text-2xl font-bold text-text">{{ plano.nome }}</h2>
              <p class="planos__description mt-1 text-sm text-text-muted">{{ plano.descricao }}</p>
            </header>

            <p class="planos__price flex flex-wrap items-baseline justify-center gap-1 pt-2">
              <span class="planos__price-value text-fluid-3xl-4xl font-bold leading-tight text-text">{{ formatCurrency(plano.preco_mensal) }}</span>
              <span class="planos__price-period text-text-muted">{{ $t('planos.perMonth') }}</span>
            </p>

            <p v-if="plano.preco_anual > 0" class="planos__yearly flex flex-wrap items-center justify-center gap-2 text-sm text-text-muted">
              <span>{{ $t('planos.orYearly', { price: formatCurrency(plano.preco_anual) }) }}</span>
              <BaseBadge variant="success" size="sm" icon="trending-down">
                {{ $t('planos.save', { percent: calcularEconomia(plano) }) }}
              </BaseBadge>
            </p>

            <ul class="planos__features mt-2 flex w-full flex-1 list-none flex-col gap-2 text-start">
              <li v-for="recurso in plano.recursos" :key="recurso" class="planos__feature flex items-start gap-2 text-sm text-text">
                <BaseIcon name="check" class="planos__feature-icon mt-nudge size-4.5 text-success" />
                <span>{{ formatRecurso(recurso) }}</span>
              </li>
            </ul>

            <dl class="planos__limits flex flex-wrap justify-center gap-2">
              <div v-for="(limite, key) in plano.limites" :key="key" class="planos__limit flex items-center gap-1 rounded-sm bg-surface-muted px-3 py-1 text-xs">
                <dt class="planos__limit-label text-text-muted">{{ formatLimiteKey(key) }}</dt>
                <dd class="planos__limit-value font-semibold text-text">{{ limite === 999999 ? '∞' : limite }}</dd>
              </div>
            </dl>

            <BaseBadge v-if="plano.dias_trial > 0" variant="warning" icon="clock" wrap class="planos__trial">
              {{ $t('planos.trialDays', { days: plano.dias_trial }) }}
            </BaseBadge>

            <div v-if="isPlanoAtual(plano) && (vigenciaPlanoAtual || pagamentoPendente)" class="planos__status flex w-full flex-col items-center gap-2">
              <BaseBadge v-if="cancelamentoAgendado" variant="warning" icon="clock" wrap class="planos__cancel-scheduled">
                {{ $t('planos.cancelScheduled') }}
              </BaseBadge>
              <p v-if="vigenciaPlanoAtual" class="planos__validity flex items-center gap-1 text-sm text-text-muted">
                <BaseIcon :name="isRecorrente && !cancelamentoAgendado ? 'refresh' : 'clock'" class="size-4" />
                <span>{{ vigenciaPlanoAtual }}</span>
              </p>
              <BaseAlert
                v-if="pagamentoPendente"
                variant="warning"
                class="planos__past-due text-start"
                :title="$t('planos.paymentPastDue')"
                :message="$t('planos.paymentPastDueHint')"
              />
            </div>

            <div class="planos__actions mt-auto flex w-full flex-col gap-2 pt-2">
              <BaseButton v-if="isPlanoAtual(plano)" variant="secondary" icon="check" block disabled>
                {{ $t('planos.currentPlan') }}
              </BaseButton>
              <!-- Com Premium recorrente a API recusa a troca (a assinatura continuaria cobrando) -->
              <p v-else-if="isRecorrente" class="planos__switch-blocked text-center text-xs text-text-muted">
                {{ $t('planos.switchBlockedRecurring') }}
              </p>
              <BaseButton
                v-else
                :variant="plano.preco_mensal > 0 ? 'primary' : 'secondary'"
                block
                :loading="isLoading"
                @click="handleAssinarPlano(plano)"
              >
                {{
                  isLoading
                    ? $t('common.salvando')
                    : plano.preco_mensal === 0
                      ? $t('planos.startFree')
                      : $t('planos.subscribeNow')
                }}
              </BaseButton>
              <BaseButton
                v-if="!isPlanoAtual(plano) && !isRecorrente && plano.preco_mensal > 0 && plano.preco_anual > 0"
                variant="secondary"
                block
                :loading="isLoading"
                @click="handleAssinarPlano(plano, 'anual')"
              >
                {{ $t('planos.subscribeYearly') }}
              </BaseButton>

              <BaseButton
                v-if="isPlanoAtual(plano) && isRecorrente"
                variant="secondary"
                icon="credit-card"
                block
                :loading="isLoadingPortal"
                :disabled="isLoading"
                @click="handleGerenciarPagamento"
              >
                {{ $t('planos.managePayment') }}
              </BaseButton>

              <BaseButton
                v-if="isPlanoAtual(plano) && podeCancelar"
                variant="ghost-danger"
                size="sm"
                icon="x-circle"
                block
                :disabled="isLoading"
                @click="showCancelModal = true"
              >
                {{ $t('planos.cancelSubscription') }}
              </BaseButton>
            </div>
          </div>
        </BaseCard>
      </li>
    </ul>

    <ConfirmModal
      v-model="showCancelModal"
      type="warning"
      :title="$t('planos.cancelSubscription')"
      :message="cancelConfirmMessage"
      :confirm-text="$t('planos.cancelSubscription')"
      :loading="isLoading"
      @confirm="handleCancelarAssinatura"
    />
  </PageContainer>
</template>

<script setup lang="ts">
import {
  BaseAlert,
  BaseBadge,
  BaseButton,
  BaseCard,
  BaseIcon,
  BaseSpinner,
  ConfirmModal,
  EmptyState,
  PageContainer,
  PageHeader,
} from '@/shared/components/ui'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import { usePlanosPage } from './PlanosPage'

const {
  planosData,
  isAuthenticated,
  isLoading,
  isLoadingPlanos,
  showCancelModal,
  podeCancelar,
  isRecorrente,
  cancelamentoAgendado,
  pagamentoPendente,
  vigenciaPlanoAtual,
  cancelConfirmMessage,
  isLoadingPortal,
  handleGerenciarPagamento,
  isPlanoAtual,
  calcularEconomia,
  formatRecurso,
  formatLimiteKey,
  formatCurrency,
  handleAssinarPlano,
  handleCancelarAssinatura,
} = usePlanosPage()
</script>

<template>
  <PageContainer size="sm" class="fcard-study">
    <PageHeader
      variant="plain"
      :title="isReview ? $t('revisao.titulo') : $t('flashcards.estudar')"
      :icon="isReview ? 'refresh' : undefined"
      back-to="/flashcards"
    >
      <div v-if="flashcard" class="fcard-study__badges flex flex-wrap gap-2">
        <BaseBadge :variant="nivelVariant" class="fcard-study__level">
          {{ getLevelText(flashcard.nivel_dificuldade) }}
        </BaseBadge>
        <BaseBadge v-if="!isReview" :variant="proxima.devido ? 'warning' : 'neutral'" icon="clock" class="fcard-study__next">
          {{ proxima.texto }}
        </BaseBadge>
      </div>

      <!-- Revisão: posição na fila e quantos faltam -->
      <div v-if="isReview && !loading && flashcard" class="fcard-study__progress mt-3 flex flex-col gap-2">
        <p class="fcard-study__progress-label flex flex-wrap justify-between gap-2 text-sm font-semibold text-text-muted">
          <span aria-hidden="true">{{ $t('revisao.posicao', { current: reviewPosition, total: reviewTotal }) }}</span>
          <span class="fcard-study__remaining" aria-live="polite">{{ $t('revisao.faltam', { n: remaining }) }}</span>
        </p>
        <BaseProgress
          :value="reviewPosition"
          :max="reviewTotal"
          :label="$t('revisao.titulo')"
          :value-text="$t('revisao.posicao', { current: reviewPosition, total: reviewTotal })"
        />
      </div>
    </PageHeader>

    <BaseSpinner v-if="loading" center size="lg" show-label :label="isReview ? $t('revisao.loading') : $t('flashcardStudy.loading')" />

    <EmptyState
      v-else-if="isReview && reviewTotal === 0"
      icon="check-circle"
      :title="$t('revisao.emptyTitle')"
      :description="$t('revisao.emptyDescription')"
    >
      <BaseButton icon="arrow-left" @click="goBack">{{ $t('flashcardStudy.backToDecks') }}</BaseButton>
    </EmptyState>

    <section v-else-if="flashcard" class="fcard-study__body flex flex-col gap-6">
      <!-- <button> nativo: Enter/Espaço viram o card; aria-pressed anuncia o lado -->
      <button
        type="button"
        class="fcard-study__card group block w-full cursor-pointer rounded-xl border-0 bg-transparent text-center text-text font-inherit perspective-card focus-visible:focus-ring"
        :class="{ 'fcard-study__card--flipped': isFlipped }"
        :aria-pressed="isFlipped"
        @click="flipCard"
      >
        <span class="fcard-study__inner grid min-h-flashcard transition-transform duration-flip ease-emphasized preserve-3d" :class="{ 'rotate-y-180': isFlipped }">
          <span class="fcard-study__face fcard-study__face--front col-start-1 row-start-1 flex flex-col items-center justify-between gap-4 rounded-xl border border-solid border-border bg-surface p-5 shadow-md backface-hidden group-hover:border-primary sm:p-6" :aria-hidden="isFlipped">
            <BaseBadge variant="primary" size="sm">{{ $t('flashcards.perguntaLabel') }}</BaseBadge>
            <span class="fcard-study__text flex flex-1 wrap-anywhere items-center justify-center whitespace-pre-line text-lg font-semibold leading-base sm:text-xl">{{ flashcard.pergunta }}</span>
            <span class="fcard-study__hint inline-flex items-center gap-2 text-sm text-text-muted">
              <BaseIcon name="refresh" />
              {{ $t('flashcardStudy.flipHintKeyboard') }}
            </span>
          </span>

          <span class="fcard-study__face fcard-study__face--back col-start-1 row-start-1 flex rotate-y-180 flex-col items-center justify-between gap-4 rounded-xl border border-solid border-border bg-surface-muted p-5 shadow-md backface-hidden group-hover:border-primary sm:p-6" :aria-hidden="!isFlipped">
            <BaseBadge variant="info" size="sm">{{ $t('flashcards.respostalabel') }}</BaseBadge>
            <span class="fcard-study__text flex flex-1 wrap-anywhere items-center justify-center whitespace-pre-line text-lg font-semibold leading-base sm:text-xl">{{ flashcard.resposta }}</span>
            <span class="fcard-study__hint inline-flex items-center gap-2 text-sm text-text-muted">
              <BaseIcon name="refresh" />
              {{ $t('flashcardStudy.flipBackHint') }}
            </span>
          </span>
        </span>
      </button>

      <!-- Fora do botão do card (não dá para ter botão dentro de botão): ouve o lado visível -->
      <div class="fcard-study__ouvir flex justify-center">
        <BotaoOuvir :texto="isFlipped ? flashcard.resposta : flashcard.pergunta" />
      </div>

      <!-- Avaliação: invisível (e fora da ordem de Tab) até o card ser virado -->
      <div
        class="fcard-study__dock text-center transition-reveal"
        :class="isFlipped ? 'fcard-study__dock--visible visible translate-y-0 opacity-100' : 'invisible translate-y-2 opacity-0'"
        role="group"
        :aria-label="$t('flashcardStudy.rateTitle')"
        :aria-busy="saving"
      >
        <p class="fcard-study__dock-title mb-3 text-sm font-medium text-text-muted">{{ $t('flashcardStudy.rateTitle') }}</p>
        <div class="fcard-study__rates grid grid-cols-3 gap-2 sm:gap-3">
          <BaseButton
            variant="danger"
            size="lg"
            icon="x-circle"
            stacked
            :disabled="saving"
            @click.stop="rateCard('hard')"
          >
            {{ $t('flashcardStudy.rateHard') }}
          </BaseButton>
          <BaseButton
            variant="primary"
            size="lg"
            icon="check-circle"
            stacked
            :disabled="saving"
            @click.stop="rateCard('good')"
          >
            {{ $t('flashcardStudy.rateGood') }}
          </BaseButton>
          <BaseButton
            variant="success"
            size="lg"
            icon="sparkles"
            stacked
            :disabled="saving"
            @click.stop="rateCard('easy')"
          >
            {{ $t('flashcardStudy.rateEasy') }}
          </BaseButton>
        </div>
        <p class="fcard-study__rate-hint mt-3 text-xs text-text-muted">{{ $t('revisao.rateHint') }}</p>
      </div>
    </section>

    <BaseModal
      v-model="showCompletionModal"
      :title="isReview ? $t('revisao.completedTitle') : $t('flashcardStudy.completedTitle')"
      :description="isReview ? $t('revisao.completedMessage') : $t('flashcardStudy.completedMessage')"
      size="sm"
      hide-close
      :close-on-overlay="false"
      :close-on-esc="false"
    >
      <div class="fcard-study__stats grid grid-cols-fit-40 gap-3">
        <StatCard
          :label="$t('flashcardStudy.wrongCount')"
          :value="stats.hard"
          icon="x-circle"
          variant="danger"
        />
        <StatCard
          :label="$t('flashcardStudy.correctCount')"
          :value="stats.good + stats.easy"
          icon="check-circle"
          variant="success"
        />
      </div>
      <template #footer>
        <BaseButton variant="secondary" icon="refresh" @click="studyAgain">
          {{ isReview ? $t('revisao.checkAgain') : $t('flashcardStudy.studyAgain') }}
        </BaseButton>
        <BaseButton icon="arrow-left" @click="goBack">{{ $t('flashcardStudy.backToDecks') }}</BaseButton>
      </template>
    </BaseModal>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  BaseBadge,
  BaseButton,
  BotaoOuvir,
  BaseIcon,
  BaseModal,
  BaseProgress,
  BaseSpinner,
  EmptyState,
  PageContainer,
  PageHeader,
  StatCard,
} from '@/shared/components/ui'
import { getNivelVariant } from '@/shared/utils/nivelDificuldade'
import { useProximaRevisao } from '../composables/useRevisao'
import { useFlashcardStudy } from './FlashcardStudy'

const {
  flashcard,
  loading,
  saving,
  isFlipped,
  isReview,
  reviewTotal,
  reviewPosition,
  remaining,
  showCompletionModal,
  stats,
  goBack,
  flipCard,
  rateCard,
  studyAgain,
  getLevelText,
} = useFlashcardStudy()

const { proximaRevisao } = useProximaRevisao()

const nivelVariant = computed(() => getNivelVariant(flashcard.value?.nivel_dificuldade))
const proxima = computed(() => proximaRevisao(flashcard.value?.proxima_revisao))
</script>

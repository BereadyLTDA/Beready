<template>
  <PageContainer size="md" class="fcard-view">
    <PageHeader variant="plain" :title="$t('flashcards.detailsTitle')" icon="book-open" back-to="/flashcards">
      <BaseBadge v-if="flashcard" :variant="nivelVariant" class="fcard-view__level">
        {{ getLevelText(flashcard.nivel_dificuldade) }}
      </BaseBadge>
      <template v-if="flashcard && !loading" #actions>
        <BaseButton icon="academic-cap" @click="studyFlashcard">{{ $t('flashcards.estudar') }}</BaseButton>
        <BaseButton v-if="canDelete()" variant="danger" icon="trash" @click="openDeleteModal">
          {{ $t('common.excluir') }}
        </BaseButton>
      </template>
    </PageHeader>

    <BaseSpinner v-if="loading" center size="lg" show-label />

    <BaseCard v-else-if="flashcard" as="article" padding="none">
      <section class="fcard-view__section flex flex-col items-start gap-3 p-5 md:p-8">
        <BaseBadge variant="primary" size="sm">{{ $t('flashcards.perguntaLabel') }}</BaseBadge>
        <p class="fcard-view__text fcard-view__text--question wrap-anywhere whitespace-pre-line text-xl font-semibold leading-relaxed text-text md:text-2xl">{{ flashcard.frente }}</p>
        <BotaoOuvir :texto="flashcard.frente" class="mt-2" />
      </section>
      <section class="fcard-view__section fcard-view__section--answer border-0 border-t border-solid border-border flex flex-col items-start gap-3 p-5 md:p-8">
        <BaseBadge variant="info" size="sm">{{ $t('flashcards.respostalabel') }}</BaseBadge>
        <p class="fcard-view__text wrap-anywhere whitespace-pre-line text-lg leading-relaxed text-text">{{ flashcard.verso }}</p>
      </section>

      <template #footer>
        <div class="fcard-view__footer flex w-full flex-wrap items-center justify-between gap-3">
          <dl v-if="flashcard.criado_em" class="fcard-view__meta flex flex-wrap gap-x-2 gap-y-1 text-sm text-text-muted">
            <dt class="font-semibold after:content-colon">{{ $t('flashcards.criadoEm') }}</dt>
            <dd>{{ formatDate(flashcard.criado_em) }}</dd>
          </dl>
          <BaseBadge :variant="proxima.devido ? 'warning' : 'neutral'" icon="clock" wrap class="fcard-view__next">
            {{ proxima.texto }}
          </BaseBadge>
        </div>
      </template>
    </BaseCard>

    <ConfirmModal
      v-model="showConfirmModal"
      :title="$t('flashcards.confirmDelete')"
      :message="$t('flashcards.deleteConfirmMessage')"
      :warning="$t('flashcards.deleteWarning')"
      :item-name="flashcard?.frente"
      :confirm-text="$t('flashcards.confirmDeleteButton')"
      type="danger"
      :loading="deleting"
      @confirm="confirmDelete"
    />
  </PageContainer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  BaseBadge,
  BaseButton,
  BotaoOuvir,
  BaseCard,
  BaseSpinner,
  ConfirmModal,
  PageContainer,
  PageHeader,
} from '@/shared/components/ui'
import { getNivelVariant } from '@/shared/utils/nivelDificuldade'
import { useProximaRevisao } from '../composables/useRevisao'
import { useFlashcardView } from './FlashcardView'

const {
  flashcard,
  loading,
  deleting,
  showConfirmModal,
  canDelete,
  studyFlashcard,
  openDeleteModal,
  confirmDelete,
  getLevelText,
  formatDate,
} = useFlashcardView()

const { proximaRevisao } = useProximaRevisao()

const nivelVariant = computed(() => getNivelVariant(flashcard.value?.nivel_dificuldade))
const proxima = computed(() => proximaRevisao(flashcard.value?.proxima_revisao))
</script>

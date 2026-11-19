<template>
  <PageContainer class="frases-prompt">
    <PageHeader
      :title="$t('frases.title')"
      :subtitle="$t('common.promptLabel', { texto: promptTexto })"
      icon="chat"
      :back-to="`/prompts/${promptId}`"
    >
      <template #actions>
        <BaseButton icon="sparkles" :loading="gerando" @click="gerar">
          {{ gerando ? $t('frases.generating') : $t('frases.generate') }}
        </BaseButton>
        <BaseButton variant="secondary" icon="plus" @click="openModal">{{ $t('frases.new') }}</BaseButton>
      </template>
    </PageHeader>

    <BaseSpinner v-if="loading" center size="lg" show-label :label="$t('frases.loading')" />

    <EmptyState
      v-else-if="frases.length === 0"
      icon="chat"
      :title="$t('frases.emptyTitle')"
      :description="$t('frases.emptyDescription')"
    >
      <BaseButton icon="plus" @click="openModal">{{ $t('frases.add') }}</BaseButton>
    </EmptyState>

    <ul v-else class="frases-prompt__grid grid list-none grid-cols-fill gap-6" role="list">
      <li v-for="frase in frases" :key="frase.id" class="frases-prompt__item flex min-w-0">
        <BaseCard as="article" padding="sm" class="frases-prompt__card flex-1">
          <div class="frases-prompt__card-top flex items-center justify-between gap-2">
            <BaseBadge variant="primary">{{ getTipoLabel(frase.tipo_frase) }}</BaseBadge>
            <div class="frases-prompt__actions flex gap-1">
              <BaseButton
                variant="ghost"
                icon="pencil"
                :aria-label="$t('common.editar')"
                @click="editFrase(frase)"
              />
              <BaseButton
                variant="ghost-danger"
                icon="trash"
                :aria-label="$t('common.excluir')"
                @click="confirmDelete(frase)"
              />
            </div>
          </div>
          <p class="frases-prompt__text mt-3 wrap-anywhere leading-relaxed text-text">{{ frase.frase_semelhante }}</p>
          <BotaoOuvir :texto="frase.frase_semelhante" class="mt-2" />
          <template #footer>
            <div class="frases-prompt__meta flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
              <span>{{ $t('frases.similarity', { valor: Math.round((frase.pontuacao_semelhante || 0) * 100) }) }}</span>
              <BaseBadge size="sm">{{ $t(getNivelLabelKey(frase.nivel_dificuldade)) }}</BaseBadge>
            </div>
          </template>
        </BaseCard>
      </li>
    </ul>

    <!-- Criar / editar -->
    <BaseModal v-model="modalOpen" :title="editingId ? $t('frases.edit') : $t('frases.new')">
      <form id="frases-prompt-form" class="flex flex-col gap-4" @submit.prevent="save">
        <BaseTextarea
          v-model="form.frase_semelhante"
          :label="$t('frases.fraseLabel')"
          :placeholder="$t('frases.frasePlaceholder')"
          :rows="3"
          required
        />
        <BaseField field-id="frases-prompt-similaridade" :label="$t('frases.similarityLabel')">
          <div class="frases-prompt__range flex items-center gap-3">
            <input
              id="frases-prompt-similaridade"
              v-model.number="form.pontuacao_semelhante"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="frases-prompt__range-input min-h-control min-w-0 flex-1 cursor-pointer accent-primary focus-visible:focus-ring"
              :aria-valuetext="`${Math.round((form.pontuacao_semelhante || 0) * 100)}%`"
            />
            <output for="frases-prompt-similaridade" class="frases-prompt__range-value min-w-14 text-end font-semibold tabular-nums text-primary">
              {{ Math.round((form.pontuacao_semelhante || 0) * 100) }}%
            </output>
          </div>
        </BaseField>
        <div class="frases-prompt__form-row grid grid-cols-fit-48 gap-4">
          <BaseSelect v-model="form.tipo_frase" :label="$t('frases.tipoLabel')" :options="tipoOptions" />
          <BaseSelect v-model="form.nivel_dificuldade" :label="$t('flashcards.dificuldade')" :options="nivelOptions" />
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="closeModal">{{ $t('common.cancelar') }}</BaseButton>
        <BaseButton type="submit" form="frases-prompt-form" :loading="saving">
          {{ saving ? $t('common.salvando') : $t('common.salvar') }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmModal
      v-model="confirmModalVisible"
      :title="$t('prompts.confirmDelete')"
      :message="$t('frases.deleteMessage')"
      :warning="$t('confirmModal.irreversible')"
      :confirm-text="$t('common.excluir')"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </PageContainer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFrasesPrompt } from './FrasesPrompt'
import {
  BaseBadge,
  BaseButton,
  BotaoOuvir,
  BaseCard,
  BaseField,
  BaseModal,
  BaseSelect,
  BaseSpinner,
  BaseTextarea,
  ConfirmModal,
  EmptyState,
  PageContainer,
  PageHeader,
  type SelectOption,
} from '@/shared/components/ui'

const { t } = useI18n()

const {
  promptId,
  promptTexto,
  loading,
  frases,
  modalOpen,
  saving,
  form,
  editingId,
  deleting,
  confirmModalVisible,
  gerando,
  gerar,
  getTipoLabel,
  getNivelLabelKey,
  openModal,
  closeModal,
  editFrase,
  save,
  confirmDelete,
  handleConfirmDelete,
} = useFrasesPrompt()

const tipoOptions = computed<SelectOption[]>(() => [
  { value: 'alternativa', label: t('frases.tipos.alternativa') },
  { value: 'sinonimo', label: t('frases.tipos.sinonimo') },
  { value: 'exemplo', label: t('frases.tipos.exemplo') },
  { value: 'relacionada', label: t('frases.tipos.relacionada') },
])

const nivelOptions = computed<SelectOption[]>(() => [
  { value: 'iniciante', label: t('common.iniciante') },
  { value: 'intermediario', label: t('common.intermediario') },
  { value: 'avancado', label: t('common.avancado') },
])
</script>

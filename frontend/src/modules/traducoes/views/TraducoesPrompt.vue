<template>
  <PageContainer class="traducoes-prompt">
    <PageHeader
      :title="$t('traducoes.title')"
      :subtitle="$t('common.promptLabel', { texto: promptTexto })"
      icon="language"
      :back-to="`/prompts/${promptId}`"
    >
      <template #actions>
        <BaseButton icon="sparkles" @click="openGerar">{{ $t('traducoes.generate') }}</BaseButton>
        <BaseButton variant="secondary" icon="plus" @click="openModal">{{ $t('traducoes.new') }}</BaseButton>
      </template>
    </PageHeader>

    <BaseSpinner v-if="loading" center size="lg" show-label :label="$t('traducoes.loading')" />

    <EmptyState
      v-else-if="traducoes.length === 0"
      icon="language"
      :title="$t('traducoes.emptyTitle')"
      :description="$t('traducoes.emptyDescription')"
    >
      <BaseButton icon="plus" @click="openModal">{{ $t('traducoes.add') }}</BaseButton>
    </EmptyState>

    <ul v-else class="traducoes-prompt__grid grid list-none grid-cols-fill gap-6" role="list">
      <li v-for="traducao in traducoes" :key="traducao.id" class="traducoes-prompt__item flex min-w-0">
        <BaseCard as="article" padding="sm" class="traducoes-prompt__card flex-1">
          <div class="traducoes-prompt__card-top flex items-center justify-between gap-2">
            <BaseBadge variant="primary" icon="language">{{ traducao.idioma_destino?.toUpperCase() || 'PT' }}</BaseBadge>
            <div class="traducoes-prompt__actions flex gap-1">
              <BaseButton
                variant="ghost"
                icon="pencil"
                :aria-label="$t('common.editar')"
                @click="editTraducao(traducao)"
              />
              <BaseButton
                variant="ghost-danger"
                icon="trash"
                :aria-label="$t('common.excluir')"
                @click="confirmDelete(traducao)"
              />
            </div>
          </div>
          <p class="traducoes-prompt__text mt-3 wrap-anywhere leading-relaxed text-text">{{ traducao.texto_traduzido }}</p>
          <BotaoOuvir :texto="traducao.texto_traduzido" :lang="traducao.idioma_destino || 'en-US'" class="mt-2" />
          <template #footer>
            <div class="traducoes-prompt__meta flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
              <span>{{ $t('traducoes.confidence', { valor: Math.round((traducao.pontuacao_confianca || 0) * 100) }) }}</span>
              <span>{{ formatDate(traducao.criado_em) }}</span>
            </div>
          </template>
        </BaseCard>
      </li>
    </ul>

    <!-- Criar / editar -->
    <BaseModal v-model="modalOpen" :title="editingId ? $t('traducoes.edit') : $t('traducoes.new')">
      <form id="traducoes-prompt-form" class="flex flex-col gap-4" @submit.prevent="save">
        <BaseTextarea
          v-model="form.texto_traduzido"
          :label="$t('traducoes.textLabel')"
          :placeholder="$t('traducoes.textPlaceholder')"
          :rows="4"
          required
        />
        <BaseSelect v-model="form.idioma_destino" :label="$t('traducoes.targetLanguage')" :options="idiomaOptions" />
        <BaseField field-id="traducoes-prompt-confianca" :label="$t('traducoes.confidenceLabel')">
          <div class="traducoes-prompt__range flex items-center gap-3">
            <input
              id="traducoes-prompt-confianca"
              v-model.number="form.pontuacao_confianca"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="traducoes-prompt__range-input min-h-control min-w-0 flex-1 cursor-pointer accent-primary focus-visible:focus-ring"
              :aria-valuetext="`${Math.round((form.pontuacao_confianca || 0) * 100)}%`"
            />
            <output for="traducoes-prompt-confianca" class="traducoes-prompt__range-value min-w-14 text-end font-semibold tabular-nums text-primary">
              {{ Math.round((form.pontuacao_confianca || 0) * 100) }}%
            </output>
          </div>
        </BaseField>
        <BaseSelect v-model="form.servico_traducao" :label="$t('traducoes.serviceLabel')" :options="servicoOptions" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="closeModal">{{ $t('common.cancelar') }}</BaseButton>
        <BaseButton type="submit" form="traducoes-prompt-form" :loading="saving">
          {{ saving ? $t('common.salvando') : $t('common.salvar') }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Gerar com IA -->
    <BaseModal v-model="gerarModalOpen" :title="$t('traducoes.generateTitle')">
      <form id="traducoes-prompt-gerar" class="flex flex-col gap-4" @submit.prevent="gerar">
        <p class="text-sm text-text-muted">{{ $t('traducoes.generateHint') }}</p>
        <BaseSelect v-model="idiomaGerar" :label="$t('traducoes.targetLanguage')" :options="idiomaOptions" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" :disabled="gerando" @click="gerarModalOpen = false">
          {{ $t('common.cancelar') }}
        </BaseButton>
        <BaseButton type="submit" form="traducoes-prompt-gerar" icon="sparkles" :loading="gerando">
          {{ gerando ? $t('traducoes.generating') : $t('traducoes.generate') }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmModal
      v-model="confirmModalVisible"
      :title="$t('prompts.confirmDelete')"
      :message="$t('traducoes.deleteMessage')"
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
import { useTraducoesPrompt } from './TraducoesPrompt'
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
  traducoes,
  modalOpen,
  saving,
  form,
  editingId,
  deleting,
  confirmModalVisible,
  gerarModalOpen,
  gerando,
  idiomaGerar,
  openGerar,
  gerar,
  formatDate,
  openModal,
  closeModal,
  editTraducao,
  save,
  confirmDelete,
  handleConfirmDelete,
} = useTraducoesPrompt()

const idiomaOptions = computed<SelectOption[]>(() => [
  { value: 'pt-BR', label: t('idiomas.ptBR') },
  { value: 'en', label: t('idiomas.en') },
  { value: 'es', label: t('idiomas.es') },
  { value: 'fr', label: t('idiomas.fr') },
  { value: 'de', label: t('idiomas.de') },
  { value: 'it', label: t('idiomas.it') },
])

// Nomes de produto (não traduzidos)
const servicoOptions: SelectOption[] = [
  { value: 'google', label: 'Google Translate' },
  { value: 'deepl', label: 'DeepL' },
  { value: 'microsoft', label: 'Microsoft Translator' },
  { value: 'openai', label: 'OpenAI' },
  // Traduções geradas pelo próprio app (POST /prompts/{id}/traducoes/gerar)
  { value: 'claude', label: 'Claude' },
]
</script>

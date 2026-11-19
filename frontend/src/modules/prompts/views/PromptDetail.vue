<template>
  <PageContainer class="prompt-detail">
    <PageHeader
      :title="$t('prompts.detailsTitle')"
      :subtitle="prompt?.texto_original || ''"
      icon="chat"
      back-to="/prompts"
    />

    <BaseCard padding="none" class="prompt-detail__panel">
      <BaseTabs v-model="activeTab" :tabs="tabs" :label="$t('prompts.detailsTitle')" embedded>
        <!-- Traduções -->
        <template #traducoes>
          <section class="prompt-detail__pane flex flex-col gap-4 p-4 md:p-6">
            <h2 class="prompt-detail__section-title text-xl font-semibold text-text">{{ $t('traducoes.title') }}</h2>

            <BaseSpinner v-if="loadingTraducoes" center show-label :label="$t('traducoes.loading')" />
            <EmptyState v-else-if="traducoes.length === 0" compact icon="language" title-tag="h3" :title="$t('prompts.noTraducoes')" />
            <ul v-else class="prompt-detail__grid grid list-none grid-cols-fill-68 gap-4" role="list">
              <li v-for="traducao in traducoes" :key="traducao.id" class="prompt-detail__item flex min-w-0">
                <BaseCard as="article" padding="sm" class="prompt-detail__card flex-1">
                  <div class="prompt-detail__card-top flex items-center justify-between gap-2">
                    <BaseBadge variant="primary" icon="language">{{ traducao.idioma_destino?.toUpperCase() || 'PT' }}</BaseBadge>
                    <BaseButton
                      variant="ghost-danger"
                      icon="trash"
                      :aria-label="$t('common.excluir')"
                      @click="confirmDeleteTraducao(traducao)"
                    />
                  </div>
                  <p class="prompt-detail__text mt-3 leading-relaxed text-text wrap-anywhere">{{ traducao.texto_traduzido }}</p>
                  <BotaoOuvir :texto="traducao.texto_traduzido" :lang="traducao.idioma_destino || 'en-US'" class="mt-2" />
                  <template #footer>
                    <div class="prompt-detail__meta flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                      <span>{{ $t('traducoes.confidence', { valor: Math.round((traducao.pontuacao_confianca || 0) * 100) }) }}</span>
                      <span>{{ formatDate(traducao.criado_em) }}</span>
                    </div>
                  </template>
                </BaseCard>
              </li>
            </ul>
          </section>
        </template>

        <!-- Imagens -->
        <template #imagens>
          <section class="prompt-detail__pane flex flex-col gap-4 p-4 md:p-6">
            <h2 class="prompt-detail__section-title text-xl font-semibold text-text">{{ $t('imagens.title') }}</h2>

            <BaseSpinner v-if="loadingImagens" center show-label :label="$t('imagens.loading')" />
            <EmptyState v-else-if="imagens.length === 0" compact icon="photo" title-tag="h3" :title="$t('prompts.noImagens')" />
            <ul v-else class="prompt-detail__grid prompt-detail__grid--images grid list-none grid-cols-fill-56 gap-4" role="list">
              <li v-for="imagem in imagens" :key="imagem.id" class="prompt-detail__item flex min-w-0">
                <BaseCard as="article" padding="none" class="prompt-detail__card flex-1">
                  <div class="prompt-detail__media relative aspect-photo bg-surface-muted">
                    <img
                      :src="imagem.url_imagem"
                      :alt="imagem.prompt_imagem || $t('imagens.altFallback')"
                      class="prompt-detail__img block h-full w-full object-cover"
                      loading="lazy"
                    />
                    <BaseButton
                      variant="danger"
                      icon="trash"
                      class="prompt-detail__media-action absolute end-2 top-2 shadow-md"
                      :aria-label="$t('common.excluir')"
                      @click="confirmDeleteImagem(imagem)"
                    />
                  </div>
                  <template #footer>
                    <div class="prompt-detail__meta flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                      <span>{{ imagem.servico_geracao || $t('imagens.aiFallback') }}</span>
                      <span>{{ formatDate(imagem.criado_em) }}</span>
                    </div>
                  </template>
                </BaseCard>
              </li>
            </ul>
          </section>
        </template>

        <!-- Frases semelhantes -->
        <template #frases>
          <section class="prompt-detail__pane flex flex-col gap-4 p-4 md:p-6">
            <h2 class="prompt-detail__section-title text-xl font-semibold text-text">{{ $t('frases.title') }}</h2>

            <BaseSpinner v-if="loadingFrases" center show-label :label="$t('frases.loading')" />
            <EmptyState v-else-if="frases.length === 0" compact icon="chat" title-tag="h3" :title="$t('prompts.noFrases')" />
            <ul v-else class="prompt-detail__grid grid list-none grid-cols-fill-68 gap-4" role="list">
              <li v-for="frase in frases" :key="frase.id" class="prompt-detail__item flex min-w-0">
                <BaseCard as="article" padding="sm" class="prompt-detail__card flex-1">
                  <div class="prompt-detail__card-top flex items-center justify-between gap-2">
                    <BaseBadge variant="primary">{{ getTipoLabel(frase.tipo_frase) }}</BaseBadge>
                    <BaseButton
                      variant="ghost-danger"
                      icon="trash"
                      :aria-label="$t('common.excluir')"
                      @click="confirmDeleteFrase(frase)"
                    />
                  </div>
                  <p class="prompt-detail__text mt-3 leading-relaxed text-text wrap-anywhere">{{ frase.frase_semelhante }}</p>
                  <BotaoOuvir :texto="frase.frase_semelhante" :lang="prompt?.idioma_original || 'en-US'" class="mt-2" />
                  <template #footer>
                    <div class="prompt-detail__meta flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                      <span>{{ $t('frases.similarity', { valor: Math.round((frase.pontuacao_semelhante || 0) * 100) }) }}</span>
                      <BaseBadge size="sm">{{ $t(getNivelLabelKey(frase.nivel_dificuldade)) }}</BaseBadge>
                    </div>
                  </template>
                </BaseCard>
              </li>
            </ul>
          </section>
        </template>
      </BaseTabs>
    </BaseCard>

    <ConfirmModal
      v-model="confirmModalVisible"
      :title="$t('prompts.confirmDelete')"
      :message="confirmMessage"
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
import { usePromptDetail } from './PromptDetail'
import {
  BaseBadge,
  BaseButton,
  BotaoOuvir,
  BaseCard,
  BaseSpinner,
  BaseTabs,
  ConfirmModal,
  EmptyState,
  PageContainer,
  PageHeader,
  type TabItem,
} from '@/shared/components/ui'

const { t } = useI18n()

const {
  prompt,
  activeTab,
  traducoes,
  imagens,
  frases,
  loadingTraducoes,
  loadingImagens,
  loadingFrases,
  confirmModalVisible,
  confirmMessage,
  deleting,
  formatDate,
  getTipoLabel,
  getNivelLabelKey,
  confirmDeleteTraducao,
  confirmDeleteImagem,
  confirmDeleteFrase,
  handleConfirmDelete,
} = usePromptDetail()

type TabId = 'traducoes' | 'imagens' | 'frases'

const tabs = computed<(TabItem & { id: TabId })[]>(() => [
  { id: 'traducoes', icon: 'language', label: t('prompts.tabTraducoes', { count: traducoes.value.length }) },
  { id: 'imagens', icon: 'photo', label: t('prompts.tabImagens', { count: imagens.value.length }) },
  { id: 'frases', icon: 'chat', label: t('prompts.tabFrases', { count: frases.value.length }) },
])
</script>


<template>
  <PageContainer size="xl" class="conquistas">
    <PageHeader :title="$t('conquistas.titulo')" :subtitle="$t('conquistas.subtitulo')" icon="trophy" back-to="/dashboard" />

    <BaseSpinner v-if="carregando" center size="lg" show-label :label="$t('common.carregando')" />

    <template v-else>
      <p class="conquistas__total mb-4 text-sm font-semibold text-text-muted" aria-live="polite">
        {{ $t('conquistas.total', { feitas: obtidas, total: conquistas.length }) }}
      </p>

      <ul class="conquistas__grid grid list-none grid-cols-fill-64 gap-4" role="list">
        <li v-for="c in conquistas" :key="c.codigo" class="flex">
          <BaseCard
            as="article"
            class="conquistas__item flex-1"
            :class="c.conquistada ? '' : 'conquistas__item--bloqueada bg-surface-muted'"
            :highlight="c.conquistada ? 'success' : undefined"
          >
            <div class="flex items-start gap-4">
              <span
                class="conquistas__icone inline-flex size-12 shrink-0 items-center justify-center rounded-full text-2xl"
                :class="c.conquistada ? 'bg-success-soft text-success' : 'bg-surface text-text-muted'"
                aria-hidden="true"
              >
                <BaseIcon :name="c.conquistada ? (c.icone as IconName) : 'lock'" />
              </span>
              <div class="min-w-0 flex-1">
                <h2 class="conquistas__nome text-base font-semibold text-text">{{ $t(`conquistas.itens.${c.codigo}.nome`) }}</h2>
                <p class="conquistas__descricao mt-1 text-sm text-text-muted">{{ $t(`conquistas.itens.${c.codigo}.descricao`) }}</p>
                <p v-if="c.conquistada && c.conquistada_em" class="conquistas__data mt-2 text-xs font-semibold text-success">
                  {{ $t('conquistas.conquistadaEm', { data: formatDate(c.conquistada_em) }) }}
                </p>
                <BaseProgress
                  v-else-if="c.alvo > 1"
                  class="mt-3"
                  size="sm"
                  :value="c.atual"
                  :max="c.alvo"
                  :label="$t(`conquistas.itens.${c.codigo}.nome`)"
                  :value-text="$t('conquistas.andamento', { atual: c.atual, alvo: c.alvo })"
                  show-label
                />
              </div>
            </div>
          </BaseCard>
        </li>
      </ul>
    </template>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { formatDate } from '@/shared/utils/intl'
import {
  BaseCard,
  BaseIcon,
  BaseProgress,
  BaseSpinner,
  PageContainer,
  PageHeader,
  type IconName,
} from '@/shared/components/ui'
import { conquistaService, type Conquista } from '../services/conquistaService'

const { t } = useI18n()
const { error } = useAlert()

const carregando = ref(true)
const conquistas = ref<Conquista[]>([])
const obtidas = computed(() => conquistas.value.filter((c) => c.conquistada).length)

onMounted(async () => {
  try {
    const { data } = await conquistaService.listar()
    conquistas.value = data.data
    // Quem abriu a página já viu as novas
    if (data.data.some((c) => c.nova)) await conquistaService.marcarVistas()
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    carregando.value = false
  }
})
</script>

<template>
  <PageContainer size="sm">
    <PageHeader
      :title="$t('preferencias.title')"
      :subtitle="$t('preferencias.subtitle')"
      icon="cog"
      back-to="/dashboard"
    />

    <BaseSpinner v-if="loading" center size="lg" show-label :label="$t('preferencias.carregando')" />

    <BaseCard v-else as="section" padding="lg">
      <form class="user-preferences__form flex flex-col gap-6" @submit.prevent="handleSave">
        <BaseSelect
          :model-value="selectedLocale"
          :label="$t('preferencias.idioma')"
          :options="localeOptions"
          @update:model-value="onLocaleChange"
        />

        <div class="user-preferences__group flex flex-col gap-2">
          <p :id="temaLabelId" class="user-preferences__label text-sm font-semibold text-text">{{ $t('preferencias.tema') }}</p>
          <div class="user-preferences__options grid grid-cols-fit-36 gap-3" role="group" :aria-labelledby="temaLabelId">
            <BaseButton
              v-for="opt in temaOptions"
              :key="opt.value"
              :variant="form.tema === opt.value ? 'primary' : 'secondary'"
              :icon="opt.icon"
              :icon-end="form.tema === opt.value ? 'check' : undefined"
              :aria-pressed="form.tema === opt.value"
              block
              @click="form.tema = opt.value"
            >
              {{ opt.label }}
            </BaseButton>
          </div>
        </div>

        <div class="user-preferences__switches flex flex-col gap-4 rounded-lg border border-solid border-border bg-surface-muted p-4">
          <BaseSwitch
            v-model="form.modo_daltonico"
            :label="$t('preferencias.modoDaltonico')"
            :hint="$t('preferencias.modoDaltonicoHelper')"
          />
          <BaseSwitch
            v-model="form.notificacoes_ativas"
            :label="$t('preferencias.notificacoes')"
            :hint="$t('preferencias.notificacoesHelper')"
          />
          <BaseSwitch
            v-model="form.som_ativo"
            :label="$t('preferencias.som')"
            :hint="$t('preferencias.somHelper')"
          />
          <BaseSwitch
            v-model="form.traducao_automatica"
            :label="$t('preferencias.traducaoAutomatica')"
            :hint="$t('preferencias.traducaoAutomaticaHelper')"
          />
        </div>

        <div class="user-preferences__group flex flex-col gap-2">
          <p :id="dificuldadeLabelId" class="user-preferences__label text-sm font-semibold text-text">
            {{ $t('preferencias.dificuldadePreferida') }}
          </p>
          <div
            class="user-preferences__options user-preferences__options--grid grid grid-cols-fit-44 gap-3"
            role="group"
            :aria-labelledby="dificuldadeLabelId"
          >
            <BaseButton
              v-for="opt in dificuldadeOptions"
              :key="opt.value"
              :variant="form.preferencia_dificuldade === opt.value ? 'primary' : 'secondary'"
              :icon="form.preferencia_dificuldade === opt.value ? 'check' : undefined"
              :aria-pressed="form.preferencia_dificuldade === opt.value"
              block
              @click="form.preferencia_dificuldade = opt.value"
            >
              {{ opt.label }}
            </BaseButton>
          </div>
        </div>

        <div class="user-preferences__group flex flex-col gap-2">
          <label :for="metaId" class="user-preferences__label text-sm font-semibold text-text">{{ $t('preferencias.metaDiaria') }}</label>
          <div class="user-preferences__meta flex flex-wrap items-center gap-4">
            <input
              :id="metaId"
              v-model.number="form.meta_diaria_minutos"
              type="range"
              min="5"
              max="120"
              step="5"
              class="user-preferences__range min-h-control min-w-0 grow basis-48 cursor-pointer accent-primary focus-visible:rounded-sm focus-visible:focus-ring"
              :aria-valuetext="$t('time.minutes', { n: form.meta_diaria_minutos })"
            />
            <output :for="metaId" class="user-preferences__meta-value inline-flex items-baseline gap-1 rounded-md bg-primary-soft px-4 py-2 text-primary-soft-text">
              <span class="user-preferences__meta-number text-xl font-bold">{{ form.meta_diaria_minutos }}</span>
              <span class="user-preferences__meta-unit text-xs">{{ $t('preferencias.metaDiariaHelper') }}</span>
            </output>
          </div>
          <div class="user-preferences__scale flex justify-between gap-1 text-xs text-text-subtle" aria-hidden="true">
            <span v-for="n in metaMarks" :key="n">{{ $t('time.minutes', { n }) }}</span>
          </div>
        </div>

        <BaseSelect
          v-model.number="form.meta_diaria_cards"
          :label="$t('preferencias.metaCards')"
          :hint="$t('preferencias.metaCardsHint')"
          :options="metaCardsOptions"
        />

        <BaseButton type="submit" size="lg" block :loading="saving">
          {{ saving ? $t('preferencias.salvando') : $t('preferencias.salvar') }}
        </BaseButton>
      </form>
    </BaseCard>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePreferencias } from '../composables/usePreferencias'
import { useAuthStore } from '@/stores/auth'
import { SUPPORTED_LOCALES } from '@/locales'
import { useAlert } from '@/shared/composables/useAlert'
import {
  BaseButton,
  BaseCard,
  BaseSelect,
  BaseSpinner,
  BaseSwitch,
  PageContainer,
  PageHeader,
  type SelectOption,
} from '@/shared/components/ui'

const { locale, t } = useI18n()
const { error } = useAlert()
const selectedLocale = ref(locale.value)
// O idioma pode mudar por fora (ex.: aplicado do usuário ao reidratar a sessão)
watch(locale, (value) => {
  selectedLocale.value = value
})

// Troca o idioma (lang/dir do documento, árabe => rtl) e grava no usuário
const onLocaleChange = async (value: string | number | null | undefined) => {
  selectedLocale.value = String(value ?? '')
  const saved = await authStore.changeLanguage(selectedLocale.value)
  if (!saved) error(t('preferencias.idiomaNaoSalvo'))
}

const localeOptions = computed<SelectOption[]>(() =>
  SUPPORTED_LOCALES.map((code) => ({ value: code, label: t(`idiomas.${code}`) })),
)

// Meta de cards revisados por dia (o dashboard mostra o andamento)
const metaCardsOptions = computed<SelectOption[]>(() =>
  [5, 10, 20, 30].map((n) => ({ value: n, label: t('preferencias.metaCardsOpcao', { n }) })),
)

const temaOptions = computed(() => [
  { value: 'claro' as const, label: t('preferencias.claro'), icon: 'sun' as const },
  { value: 'escuro' as const, label: t('preferencias.escuro'), icon: 'moon' as const },
])

const dificuldadeOptions = computed(() => [
  { value: 'iniciante' as const, label: t('preferencias.iniciante') },
  { value: 'intermediario' as const, label: t('preferencias.intermediario') },
  { value: 'avancado' as const, label: t('preferencias.avancado') },
  { value: 'adaptativo' as const, label: t('preferencias.adaptativo') },
])

const metaMarks = [5, 30, 60, 90, 120]

const uid = useId()
const temaLabelId = `pref-tema-${uid}`
const dificuldadeLabelId = `pref-dificuldade-${uid}`
const metaId = `pref-meta-${uid}`

const authStore = useAuthStore()
const { form, loading, saving, fetchPreferencias, savePreferencias } = usePreferencias()

const handleSave = async () => {
  const userId = authStore.user?.id
  if (userId) {
    await savePreferencias(userId)
  }
}

onMounted(async () => {
  const userId = authStore.user?.id
  if (userId) {
    try {
      await fetchPreferencias(userId)
    } catch {
      // erro já exibido por usePreferencias
    }
  }
})
</script>

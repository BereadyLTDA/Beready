<template>
  <PageContainer size="xl">
    <PageHeader
      :title="$t('dashboard.welcome', { name: userName })"
      :subtitle="motivationalMessage"
      icon="home"
    />

    <!-- Meta do dia: cards revisados hoje x meta das preferências -->
    <BaseCard v-if="meta" as="section" class="dashboard__meta mb-4" :aria-label="$t('meta.titulo')">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0 flex-1 basis-64">
          <p class="text-sm font-semibold text-text">
            {{ meta.feitos_hoje >= meta.meta ? $t('meta.batida') : $t('meta.titulo') }}
          </p>
          <BaseProgress
            class="mt-2"
            :value="Math.min(meta.feitos_hoje, meta.meta)"
            :max="meta.meta"
            :label="$t('meta.titulo')"
            :value-text="$t('meta.andamento', { feitos: meta.feitos_hoje, meta: meta.meta })"
            :variant="meta.feitos_hoje >= meta.meta ? 'success' : 'primary'"
            show-label
          />
        </div>
        <BaseButton v-if="meta.feitos_hoje < meta.meta" to="/flashcards/revisao" icon="refresh" size="sm">{{ $t('meta.revisar') }}</BaseButton>
        <BaseButton v-else to="/conquistas" variant="secondary" icon="trophy" size="sm">{{ $t('conquistas.ver') }}</BaseButton>
      </div>
    </BaseCard>

    <!-- Estatísticas -->
    <div class="dashboard__stats grid grid-cols-fill-56 gap-4">
      <!-- Repetição espaçada: acesso direto à fila do dia -->
      <StatCard
        :label="$t('revisao.paraRevisarHojeLabel')"
        :value="paraRevisar"
        icon="refresh"
        :variant="paraRevisar > 0 ? 'warning' : 'success'"
        :hint="paraRevisar > 0 ? $t('revisao.revisarAgora') : $t('revisao.emDia')"
        :to="paraRevisar > 0 ? '/flashcards/revisao' : '/flashcards'"
      />
      <StatCard
        :label="$t('dashboard.flashcardsCount')"
        :value="stats.flashcardsCount || 0"
        icon="document"
        variant="info"
      />
      <StatCard
        :label="$t('dashboard.acertoRate')"
        :value="`${stats.acertoRate || 0}%`"
        icon="check-circle"
        variant="success"
      />
      <StatCard
        :label="$t('dashboard.sequencia')"
        :value="stats.sequenciaAtual || 0"
        icon="lightning-bolt"
        variant="primary"
      />
      <StatCard
        :label="$t('dashboard.tempoEstudo')"
        :value="stats.tempoEstudo || $t('time.minutes', { n: 0 })"
        icon="clock"
        variant="warning"
      />
    </div>

    <!-- Progresso geral -->
    <BaseCard as="section" :title="$t('dashboard.progressoGeral')">
      <BaseProgress
        :value="stats.progressoGeral || 0"
        :label="$t('dashboard.progressoGeral')"
        :value-text="$t('dashboard.percentComplete', { percent: stats.progressoGeral || 0 })"
        size="lg"
      />
      <p class="dashboard__progress-text mt-3 text-sm text-text-muted">
        {{ $t('dashboard.percentComplete', { percent: stats.progressoGeral || 0 }) }} -
        {{ $t('dashboard.continueAssim') }}
      </p>
    </BaseCard>

    <!-- Funcionalidades -->
    <ul class="dashboard__features grid list-none grid-cols-fill gap-5">
      <li v-for="feature in features" :key="feature.to">
        <BaseCard as="article" interactive class="dashboard__feature relative h-full">
          <div class="dashboard__feature-body flex items-start gap-4">
            <span class="dashboard__feature-icon inline-flex size-13 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-2xl text-primary-soft-text" aria-hidden="true">
              <BaseIcon :name="feature.icon" />
            </span>
            <div class="dashboard__feature-content flex min-w-0 flex-col gap-1">
              <h2 class="dashboard__feature-title text-lg font-semibold leading-tight">
                <router-link :to="feature.to" class="dashboard__feature-link text-text no-underline after:absolute after:inset-0 after:rounded-inherit after:content-empty focus-visible:outline-hidden focus-visible:after:rounded-xl focus-visible:after:focus-ring-inset">
                  {{ feature.title }}
                </router-link>
              </h2>
              <p class="dashboard__feature-description text-sm text-text-muted">{{ feature.description }}</p>
              <span class="dashboard__feature-cta mt-2 inline-flex items-center gap-1 text-sm font-semibold text-primary" aria-hidden="true">
                {{ feature.cta }}
                <BaseIcon name="arrow-right" class="dashboard__feature-cta-icon size-em" />
              </span>
            </div>
          </div>
        </BaseCard>
      </li>
    </ul>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { useRevisaoPendentes } from '@/modules/flashcards/composables/useRevisao'
import { useConquistas } from '@/modules/conquistas/composables/useConquistas'
import { conquistaService, type MetaDiaria } from '@/modules/conquistas/services/conquistaService'
import {
  BaseButton,
  BaseCard,
  BaseIcon,
  BaseProgress,
  PageContainer,
  PageHeader,
  StatCard,
  type IconName,
} from '@/shared/components/ui'
import { useDashboard } from './DashboardPage'

const { t } = useI18n()
const { userName, stats, motivationalMessage } = useDashboard()
const { total: paraRevisar, carregar: carregarRevisao } = useRevisaoPendentes()

// O guard manda para cá com ?error=no_permission quando a rota exige uma permissão que falta
const route = useRoute()
const router = useRouter()
const { warning } = useAlert()
const meta = ref<MetaDiaria | null>(null)
const { checarNovas } = useConquistas()

onMounted(() => {
  carregarRevisao()
  conquistaService
    .metaDiaria()
    .then(({ data }) => (meta.value = data.data))
    .catch(() => undefined)
  checarNovas()
  if (route.query.error === 'no_permission') {
    warning(t('admin.noPermissionAction'))
    router.replace({ query: {} })
  }
})

interface DashboardFeature {
  to: string
  icon: IconName
  title: string
  description: string
  cta: string
}

const features = computed<DashboardFeature[]>(() => [
  {
    to: '/flashcards',
    icon: 'document',
    title: t('common.flashcards'),
    description: t('dashboard.features.flashcards.desc'),
    cta: t('dashboard.features.flashcards.link'),
  },
  {
    to: '/quizes',
    icon: 'clipboard',
    title: t('common.quizes'),
    description: t('dashboard.features.quizes.desc'),
    cta: t('dashboard.features.quizes.link'),
  },
  {
    to: '/prompts',
    icon: 'chat',
    title: t('prompts.title'),
    description: t('dashboard.features.prompts.desc'),
    cta: t('dashboard.features.prompts.link'),
  },
  {
    to: '/conquistas',
    icon: 'trophy',
    title: t('conquistas.titulo'),
    description: t('conquistas.cardDescricao'),
    cta: t('conquistas.ver'),
  },
  {
    to: '/pronuncia',
    icon: 'microphone',
    title: t('pronuncia.titulo'),
    description: t('pronuncia.cardDescricao'),
    cta: t('pronuncia.cardLink'),
  },
  {
    to: '/tags',
    icon: 'tag',
    title: t('common.tags'),
    description: t('dashboard.features.tags.desc'),
    cta: t('dashboard.features.tags.link'),
  },
  {
    to: '/progresso',
    icon: 'chart-bar',
    title: t('common.progresso'),
    description: t('dashboard.features.progresso.desc'),
    cta: t('dashboard.features.progresso.link'),
  },
  {
    to: '/preferencias',
    icon: 'cog',
    title: t('common.preferencias'),
    description: t('dashboard.features.preferencias.desc'),
    cta: t('dashboard.features.preferencias.link'),
  },
])
</script>

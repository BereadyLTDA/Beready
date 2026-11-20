<template>
  <PageContainer size="xl" class="progress-dash">
    <PageHeader
      :title="$t('progresso.title')"
      :subtitle="$t('progresso.subtitle')"
      icon="chart-bar"
      back-to="/dashboard"
    />

    <BaseSpinner v-if="loading" center size="lg" show-label :label="$t('progresso.carregando')" />

    <ul v-else class="progress-dash__grid grid list-none grid-cols-fill-64 gap-4" role="list">
      <li v-for="stat in stats" :key="stat.label" class="flex">
        <StatCard :label="stat.label" :value="stat.value" :icon="stat.icon" :variant="stat.variant" class="flex-1" />
      </li>
    </ul>

    <!-- Gráficos (SVG próprio; cada um tem a tabela equivalente para leitores de tela) -->
    <section v-if="historico" class="progress-dash__graficos mt-6 grid grid-cols-fit-80 gap-4" :aria-label="$t('progresso.graficos.titulo')">
      <BaseCard as="article">
        <GraficoBarras
          :titulo="$t('progresso.graficos.revisoesPorDia')"
          :itens="revisoesPorDia"
          :coluna-rotulo="$t('progresso.graficos.dia')"
          :coluna-valor="$t('progresso.graficos.revisoes')"
          :resumo="$t('progresso.graficos.resumoRevisoes', { total: totalRevisoes })"
        />
      </BaseCard>
      <BaseCard as="article">
        <GraficoLinha
          :titulo="$t('progresso.graficos.acertosPorSemana')"
          :itens="acertosPorSemana"
          :coluna-rotulo="$t('progresso.graficos.semana')"
          :coluna-valor="$t('progresso.graficos.taxa')"
          :resumo="$t('progresso.graficos.resumoAcertos', { taxa: ultimaTaxa })"
        />
      </BaseCard>
      <BaseCard as="article">
        <GraficoLinha
          :titulo="$t('progresso.graficos.pronunciaPorSemana')"
          :itens="pronunciaPorSemana"
          :coluna-rotulo="$t('progresso.graficos.semana')"
          :coluna-valor="$t('progresso.graficos.media')"
          :resumo="$t('progresso.graficos.resumoPronuncia')"
        />
      </BaseCard>
      <BaseCard as="article">
        <h2 class="mb-3 text-sm font-semibold text-text">{{ $t('progresso.graficos.distribuicao') }}</h2>
        <!-- Barra empilhada: novos, aprendendo e dominados -->
        <div class="flex h-4 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
          <span v-for="parte in distribuicao" :key="parte.chave" :class="parte.cor" :style="{ width: `${parte.pct}%` }" />
        </div>
        <ul class="mt-3 flex list-none flex-col gap-1 text-sm" role="list">
          <li v-for="parte in distribuicao" :key="`l-${parte.chave}`" class="flex items-center gap-2">
            <span class="inline-block size-3 rounded-full" :class="parte.cor" aria-hidden="true" />
            <span class="flex-1 text-text">{{ $t(`progresso.graficos.${parte.chave}`) }}</span>
            <span class="tabular-nums text-text-muted">{{ parte.valor }} ({{ parte.pct }}%)</span>
          </li>
        </ul>
      </BaseCard>
    </section>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  BaseCard,
  BaseSpinner,
  PageContainer,
  PageHeader,
  StatCard,
  type IconName,
  type StatusVariant,
} from '@/shared/components/ui'
import { useProgressoDashboard } from './ProgressoDashboard'
import GraficoBarras from '../components/GraficoBarras.vue'
import GraficoLinha from '../components/GraficoLinha.vue'
import { formatDate } from '@/shared/utils/intl'

const { t } = useI18n()
const { progresso, loading, formatarTempo, historico } = useProgressoDashboard()

// "2026-11-20" -> data curta no idioma do usuário (sem conversão de fuso: a data já vem local)
const curta = (iso: string) => formatDate(`${iso}T12:00:00`, { day: '2-digit', month: '2-digit' })
const longa = (iso: string) => formatDate(`${iso}T12:00:00`)

const revisoesPorDia = computed(() =>
  (historico.value?.revisoes_por_dia ?? []).map((d) => ({ rotulo: curta(d.dia), rotuloCompleto: longa(d.dia), valor: d.total })),
)
const totalRevisoes = computed(() => revisoesPorDia.value.reduce((s, d) => s + d.valor, 0))
const acertosPorSemana = computed(() =>
  (historico.value?.acertos_por_semana ?? []).map((s) => ({
    rotulo: curta(s.semana),
    rotuloCompleto: t('progresso.graficos.semanaDe', { data: longa(s.semana) }),
    valor: s.taxa,
    temDados: s.total > 0,
  })),
)
const ultimaTaxa = computed(() => [...acertosPorSemana.value].reverse().find((s) => s.temDados)?.valor ?? 0)
const pronunciaPorSemana = computed(() =>
  (historico.value?.pronuncia_por_semana ?? []).map((s) => ({
    rotulo: curta(s.semana),
    rotuloCompleto: t('progresso.graficos.semanaDe', { data: longa(s.semana) }),
    valor: s.media,
    temDados: s.tentativas > 0,
  })),
)
const distribuicao = computed(() => {
  const d = historico.value?.distribuicao_cards ?? { novos: 0, aprendendo: 0, dominados: 0 }
  const total = Math.max(1, d.novos + d.aprendendo + d.dominados)
  return [
    { chave: 'novos', valor: d.novos, cor: 'bg-info', pct: Math.round((d.novos / total) * 100) },
    { chave: 'aprendendo', valor: d.aprendendo, cor: 'bg-warning', pct: Math.round((d.aprendendo / total) * 100) },
    { chave: 'dominados', valor: d.dominados, cor: 'bg-success', pct: Math.round((d.dominados / total) * 100) },
  ]
})

interface ProgressStat {
  label: string
  value: string | number
  icon: IconName
  variant: 'primary' | 'neutral' | StatusVariant
}

// A cor do ícone é só decorativa: o rótulo de cada card diz o que o número representa
const stats = computed<ProgressStat[]>(() => [
  {
    label: t('progresso.vocabularioAprendido'),
    value: progresso.value.vocabulario_aprendido || 0,
    icon: 'book-open',
    variant: 'primary',
  },
  {
    label: t('progresso.flashcardsConcluidos'),
    value: progresso.value.flashcards_concluidos || 0,
    icon: 'check-circle',
    variant: 'success',
  },
  {
    label: t('progresso.quizesConcluidos'),
    value: progresso.value.quizes_concluidos || 0,
    icon: 'clipboard',
    variant: 'info',
  },
  {
    label: t('progresso.tempoTotalEstudo'),
    value: formatarTempo(progresso.value.tempo_total_estudo || 0),
    icon: 'clock',
    variant: 'neutral',
  },
  {
    label: t('progresso.sequenciaAtual'),
    value: progresso.value.sequencia_atual || 0,
    icon: 'lightning-bolt',
    variant: 'warning',
  },
  {
    label: t('progresso.maiorSequencia'),
    value: progresso.value.maior_sequencia || 0,
    icon: 'sparkles',
    variant: 'primary',
  },
])
</script>

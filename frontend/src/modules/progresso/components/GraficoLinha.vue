<template>
  <figure class="grafico-linha m-0">
    <figcaption class="mb-3 text-sm font-semibold text-text">{{ titulo }}</figcaption>
    <!-- Escala fixa de 0 a 100 (percentuais); semanas sem dados ficam sem ponto -->
    <svg :viewBox="`0 0 ${LARGURA} ${ALTURA}`" class="block h-auto w-full overflow-visible" role="img" :aria-label="resumo">
      <line
        v-for="y in [0, 50, 100]"
        :key="`g-${y}`"
        :x1="MARGEM"
        :x2="LARGURA"
        :y1="posY(y)"
        :y2="posY(y)"
        class="stroke-border"
        stroke-width="1"
        :stroke-dasharray="y === 0 ? undefined : '4 4'"
      />
      <text v-for="y in [0, 50, 100]" :key="`e-${y}`" :x="MARGEM - 6" :y="posY(y) + 3" text-anchor="end" class="fill-current text-text-muted" font-size="15">
        {{ y }}%
      </text>
      <polyline v-if="linha" :points="linha" fill="none" class="stroke-primary" stroke-width="2.5" stroke-linejoin="round" />
      <circle
        v-for="p in pontos"
        :key="`p-${p.i}`"
        :cx="p.x"
        :cy="p.y"
        r="4"
        class="fill-surface stroke-primary"
        stroke-width="2"
      >
        <title>{{ itens[p.i]?.rotuloCompleto ?? itens[p.i]?.rotulo }}: {{ itens[p.i]?.valor }}%</title>
      </circle>
      <text
        v-for="(item, i) in itens"
        v-show="mostraRotulo(i)"
        :key="`r-${item.rotulo}`"
        :x="posX(i)"
        :y="ALTURA - 2"
        text-anchor="middle"
        class="fill-current text-text-muted"
        font-size="15"
      >
        {{ item.rotulo }}
      </text>
    </svg>
    <details class="mt-2 text-sm">
      <summary class="cursor-pointer text-text-muted">{{ $t('progresso.graficos.verTabela') }}</summary>
      <table class="mt-2 w-full border-collapse">
        <thead>
          <tr>
            <th scope="col" class="py-1 text-start font-semibold">{{ colunaRotulo }}</th>
            <th scope="col" class="py-1 text-end font-semibold">{{ colunaValor }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in itens" :key="`t-${item.rotulo}`" class="border-0 border-t border-solid border-border">
            <td class="py-1">{{ item.rotuloCompleto ?? item.rotulo }}</td>
            <td class="py-1 text-end tabular-nums">{{ item.temDados === false ? '-' : `${item.valor}%` }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </figure>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface PontoGrafico {
  rotulo: string
  rotuloCompleto?: string
  /** 0 a 100 */
  valor: number
  /** false = semana sem atividade (sem ponto no gráfico) */
  temDados?: boolean
}

const props = defineProps<{
  titulo: string
  itens: PontoGrafico[]
  colunaRotulo: string
  colunaValor: string
  resumo: string
}>()

const LARGURA = 600
const ALTURA = 180
const MARGEM = 52
const TOPO = 8
const BASE = ALTURA - 26

const posX = (i: number) => MARGEM + ((LARGURA - MARGEM - 8) * i) / Math.max(1, props.itens.length - 1) + 4
const posY = (v: number) => BASE - ((BASE - TOPO) * Math.max(0, Math.min(100, v))) / 100

const pontos = computed(() =>
  props.itens.flatMap((item, i) => (item.temDados === false ? [] : [{ i, x: posX(i), y: posY(item.valor) }])),
)
const linha = computed(() => (pontos.value.length > 1 ? pontos.value.map((p) => `${p.x},${p.y}`).join(' ') : ''))
const intervaloRotulos = computed(() => Math.max(1, Math.ceil(props.itens.length / 4)))
// O último rótulo só aparece se não ficar colado no anterior
const mostraRotulo = (i: number) => {
  const n = props.itens.length
  if (i % intervaloRotulos.value === 0) return n - 1 - i >= intervaloRotulos.value / 2 || i === n - 1
  return i === n - 1 && (n - 1) % intervaloRotulos.value >= intervaloRotulos.value / 2
}
</script>

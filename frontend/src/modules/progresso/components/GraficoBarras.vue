<template>
  <figure class="grafico-barras m-0">
    <figcaption class="mb-3 text-sm font-semibold text-text">{{ titulo }}</figcaption>
    <!-- Só visual: os números estão na tabela logo abaixo -->
    <svg
      :viewBox="`0 0 ${LARGURA} ${ALTURA}`"
      class="block h-auto w-full overflow-visible"
      role="img"
      :aria-label="resumo"
    >
      <line :x1="0" :x2="LARGURA" :y1="BASE" :y2="BASE" class="stroke-border" stroke-width="1" />
      <rect
        v-for="(item, i) in itens"
        :key="item.rotulo"
        :x="i * passo + passo * 0.15"
        :y="BASE - altura(item.valor)"
        :width="passo * 0.7"
        :height="Math.max(altura(item.valor), item.valor > 0 ? 2 : 0)"
        rx="2"
        class="fill-primary"
      >
        <title>{{ item.rotuloCompleto ?? item.rotulo }}: {{ item.valor }}</title>
      </rect>
      <text
        v-for="(item, i) in itens"
        v-show="mostraRotulo(i)"
        :key="`r-${item.rotulo}`"
        :x="i * passo + passo / 2"
        :y="ALTURA - 4"
        text-anchor="middle"
        class="fill-current text-text-muted"
        font-size="15"
      >
        {{ item.rotulo }}
      </text>
    </svg>
    <details class="mt-2 text-sm">
      <summary class="cursor-pointer text-text-muted">{{ $t('progresso.graficos.verTabela') }}</summary>
      <table class="mt-2 w-full border-collapse text-start">
        <thead>
          <tr>
            <th scope="col" class="py-1 text-start font-semibold">{{ colunaRotulo }}</th>
            <th scope="col" class="py-1 text-end font-semibold">{{ colunaValor }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in itens" :key="`t-${item.rotulo}`" class="border-0 border-t border-solid border-border">
            <td class="py-1">{{ item.rotuloCompleto ?? item.rotulo }}</td>
            <td class="py-1 text-end tabular-nums">{{ item.valor }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </figure>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface ItemGrafico {
  rotulo: string
  /** Rótulo longo, usado na dica e na tabela */
  rotuloCompleto?: string
  valor: number
}

const props = defineProps<{
  titulo: string
  itens: ItemGrafico[]
  colunaRotulo: string
  colunaValor: string
  /** Resumo lido por leitores de tela no lugar do desenho */
  resumo: string
}>()

const LARGURA = 600
const ALTURA = 180
const BASE = ALTURA - 26

const passo = computed(() => LARGURA / Math.max(1, props.itens.length))
const maximo = computed(() => Math.max(1, ...props.itens.map((i) => i.valor)))
const altura = (valor: number) => (valor / maximo.value) * (BASE - 8)
// Em telas estreitas, rótulos demais se sobrepõem: mostra um a cada N
const intervaloRotulos = computed(() => Math.max(1, Math.ceil(props.itens.length / 5)))
// O último rótulo só aparece se não ficar colado no anterior
const mostraRotulo = (i: number) => {
  const n = props.itens.length
  if (i % intervaloRotulos.value === 0) return n - 1 - i >= intervaloRotulos.value / 2 || i === n - 1
  return i === n - 1 && (n - 1) % intervaloRotulos.value >= intervaloRotulos.value / 2
}
</script>

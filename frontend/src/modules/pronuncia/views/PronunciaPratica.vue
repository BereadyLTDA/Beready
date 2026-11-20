<template>
  <PageContainer size="md" class="pronuncia">
    <PageHeader :title="$t('pronuncia.titulo')" :subtitle="$t('pronuncia.subtitulo')" icon="microphone" back-to="/dashboard" />

    <BaseSpinner v-if="carregando" center size="lg" show-label :label="$t('common.carregando')" />

    <div v-else class="pronuncia__body flex flex-col gap-6">
      <BaseAlert v-if="!reconhecimento.suportado" variant="warning" :message="$t('pronuncia.naoSuportado')" />

      <BaseCard v-if="fraseAtual" as="section" class="pronuncia__card" :aria-label="$t('pronuncia.fraseAtual')">
        <p class="pronuncia__contador mb-2 text-sm text-text-muted">
          {{ $t('pronuncia.contador', { atual: indice + 1, total: frases.length }) }}
        </p>
        <!-- Depois de falar, as palavras que faltaram ficam destacadas -->
        <p class="pronuncia__frase wrap-anywhere text-fluid-2xl-3xl font-semibold leading-snug text-text" lang="en">
          <template v-for="(p, i) in palavrasDaFrase" :key="i">
            <mark v-if="p.faltou" class="rounded bg-danger-soft px-1 text-danger">{{ p.texto }}</mark>
            <span v-else>{{ p.texto }}</span>{{ ' ' }}
          </template>
        </p>

        <div class="pronuncia__acoes mt-5 flex flex-wrap items-center gap-3">
          <BotaoOuvir :texto="fraseAtual.texto" lang="en-US" />
          <BaseButton
            v-if="reconhecimento.suportado"
            :variant="reconhecimento.ouvindo.value ? 'danger' : 'primary'"
            icon="microphone"
            :loading="enviando"
            :aria-pressed="reconhecimento.ouvindo.value"
            @click="alternarMicrofone"
          >
            {{ reconhecimento.ouvindo.value ? $t('pronuncia.ouvindo') : $t('pronuncia.falar') }}
          </BaseButton>
        </div>

        <div class="pronuncia__resultado mt-5" aria-live="polite">
          <template v-if="resultado">
            <BaseProgress
              :value="resultado.nota"
              :label="$t('pronuncia.nota')"
              :value-text="$t('pronuncia.notaTexto', { nota: resultado.nota })"
              :variant="resultado.nota >= 85 ? 'success' : resultado.nota >= 60 ? 'warning' : 'danger'"
              show-label
            />
            <p class="pronuncia__dito mt-3 text-sm text-text-muted">
              {{ $t('pronuncia.voceDisse') }}
              <span lang="en" class="font-semibold text-text">"{{ reconhecido || '...' }}"</span>
            </p>
            <p class="pronuncia__mensagem mt-1 text-sm text-text">{{ mensagemNota }}</p>
          </template>
          <p v-else-if="erroMicrofone" class="text-sm text-danger" role="alert">{{ erroMicrofone }}</p>
        </div>

        <div class="pronuncia__navegacao mt-5 flex flex-wrap justify-end gap-3">
          <BaseButton v-if="resultado" variant="secondary" icon="refresh" @click="tentarDeNovo">
            {{ $t('pronuncia.tentarDeNovo') }}
          </BaseButton>
          <BaseButton variant="secondary" icon-end="arrow-right" @click="proxima">{{ $t('pronuncia.proxima') }}</BaseButton>
        </div>
      </BaseCard>

      <ul class="pronuncia__resumo grid list-none grid-cols-fill-48 gap-4" role="list">
        <li class="flex">
          <StatCard class="flex-1" icon="microphone" :label="$t('pronuncia.tentativas')" :value="resumo.total" />
        </li>
        <li class="flex">
          <StatCard class="flex-1" icon="chart-bar" variant="info" :label="$t('pronuncia.media')" :value="`${resumo.media}%`" />
        </li>
        <li class="flex">
          <StatCard class="flex-1" icon="star" variant="success" :label="$t('pronuncia.melhor')" :value="`${resumo.melhor}%`" />
        </li>
      </ul>
    </div>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { useReconhecimentoVoz } from '@/shared/composables/useReconhecimentoVoz'
import { useConquistas } from '@/modules/conquistas/composables/useConquistas'
import {
  BaseAlert,
  BaseButton,
  BaseCard,
  BaseProgress,
  BaseSpinner,
  BotaoOuvir,
  PageContainer,
  PageHeader,
  StatCard,
} from '@/shared/components/ui'
import { pronunciaService, type FrasePronuncia, type PronunciaResultado } from '../services/pronunciaService'

const { t } = useI18n()
const { error } = useAlert()
const reconhecimento = useReconhecimentoVoz()
const { checarNovas } = useConquistas()

const carregando = ref(true)
const enviando = ref(false)
const frases = ref<FrasePronuncia[]>([])
const indice = ref(0)
const resultado = ref<PronunciaResultado | null>(null)
const reconhecido = ref('')
const erroMicrofone = ref('')
const resumo = reactive({ total: 0, media: 0, melhor: 0 })

const fraseAtual = computed(() => frases.value[indice.value] ?? null)

// Mesma normalização do servidor, só para marcar na tela as palavras que faltaram
const normalizar = (s: string) =>
  s
    .toLowerCase()
    .replace(/[‘’]/g, "'")
    .replace(/[^\p{L}\p{N}']+/gu, '')

const palavrasDaFrase = computed(() => {
  const faltando = [...(resultado.value?.faltando ?? [])]
  return (fraseAtual.value?.texto ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .map((texto) => {
      const i = faltando.indexOf(normalizar(texto))
      if (i >= 0) faltando.splice(i, 1)
      return { texto, faltou: i >= 0 }
    })
})

const mensagemNota = computed(() => {
  const nota = resultado.value?.nota ?? 0
  if (nota >= 85) return t('pronuncia.otimo')
  if (nota >= 60) return t('pronuncia.quase')
  return t('pronuncia.deNovo')
})

const carregarResumo = async () => {
  const { data } = await pronunciaService.resumo()
  Object.assign(resumo, { total: data.data.total, media: data.data.media, melhor: data.data.melhor })
}

onMounted(async () => {
  try {
    const [{ data }] = await Promise.all([pronunciaService.frases(10), carregarResumo()])
    frases.value = data.data
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    carregando.value = false
  }
})

const alternarMicrofone = async () => {
  if (reconhecimento.ouvindo.value) {
    reconhecimento.parar()
    return
  }
  const frase = fraseAtual.value
  if (!frase) return
  erroMicrofone.value = ''
  resultado.value = null
  try {
    reconhecido.value = await reconhecimento.ouvir('en-US')
  } catch (err: unknown) {
    const codigo = (err as Error).message
    erroMicrofone.value =
      codigo === 'not-allowed' || codigo === 'service-not-allowed'
        ? t('pronuncia.semPermissao')
        : t('pronuncia.erroMicrofone')
    return
  }
  enviando.value = true
  try {
    const { data } = await pronunciaService.registrar(frase.texto, reconhecido.value)
    resultado.value = data.data
    await carregarResumo()
    checarNovas()
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    enviando.value = false
  }
}

const tentarDeNovo = () => {
  resultado.value = null
  reconhecido.value = ''
}

const proxima = () => {
  tentarDeNovo()
  erroMicrofone.value = ''
  indice.value = (indice.value + 1) % Math.max(1, frases.value.length)
}
</script>

<template>
  <PageContainer size="md" class="nivelamento">
    <PageHeader :title="$t('nivelamento.titulo')" :subtitle="$t('nivelamento.subtitulo')" icon="academic-cap" back-to="/dashboard" />

    <BaseSpinner v-if="carregando" center size="lg" show-label :label="$t('common.carregando')" />

    <!-- Resultado (acabou de terminar ou já tinha feito) -->
    <BaseCard v-else-if="resultado" as="section" class="nivelamento__resultado text-center" highlight="success">
      <p class="text-sm font-semibold text-text-muted">{{ $t('nivelamento.seuNivel') }}</p>
      <p class="nivelamento__nivel mt-2 text-5xl font-bold text-primary">{{ resultado.nivel }}</p>
      <p class="mt-1 text-lg font-semibold text-text">{{ $t(`nivelamento.niveis.${resultado.nivel}.nome`) }}</p>
      <p class="mx-auto mt-3 max-w-measure text-text-muted">{{ $t(`nivelamento.niveis.${resultado.nivel}.descricao`) }}</p>
      <p class="mt-4 text-sm text-text-muted">
        {{ $t('nivelamento.acertos', { acertos: resultado.acertos, total: resultado.total }) }}
        · {{ $t('nivelamento.refazerEm', { data: formatDate(resultado.pode_refazer_em) }) }}
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <BaseButton to="/flashcards" icon="document">{{ $t('nivelamento.comecarEstudar') }}</BaseButton>
        <BaseButton to="/dashboard" variant="secondary">{{ $t('common.voltarDashboard') }}</BaseButton>
      </div>
    </BaseCard>

    <!-- Questão atual -->
    <BaseCard v-else-if="estado?.questao" as="section" class="nivelamento__questao" :aria-label="$t('nivelamento.questao')">
      <BaseProgress
        :value="estado.respondidas"
        :max="estado.total"
        :label="$t('nivelamento.progresso')"
        :value-text="$t('nivelamento.posicao', { atual: estado.respondidas + 1, total: estado.total })"
        show-label
      />
      <fieldset class="mt-6 border-0 p-0" :disabled="enviando">
        <legend class="nivelamento__enunciado mb-4 text-xl font-semibold text-text" lang="en">{{ estado.questao.enunciado }}</legend>
        <div class="flex flex-col gap-3">
          <BaseButton
            v-for="(alt, i) in estado.questao.alternativas"
            :key="`${estado.questao.id}-${i}`"
            variant="secondary"
            block
            class="justify-start"
            lang="en"
            @click="responder(i)"
          >
            {{ alt }}
          </BaseButton>
        </div>
      </fieldset>
      <p class="mt-4 text-sm text-text-muted" aria-live="polite">{{ feedback }}</p>
    </BaseCard>

    <!-- Antes de começar -->
    <BaseCard v-else as="section" class="nivelamento__intro">
      <h2 class="text-xl font-semibold text-text">{{ $t('nivelamento.introTitulo') }}</h2>
      <ul class="mt-3 flex list-disc flex-col gap-1 ps-5 text-text-muted">
        <li>{{ $t('nivelamento.introQuestoes') }}</li>
        <li>{{ $t('nivelamento.introAdaptativo') }}</li>
        <li>{{ $t('nivelamento.introResultado') }}</li>
      </ul>
      <div class="mt-6 flex flex-wrap gap-3">
        <BaseButton icon="arrow-right" :loading="enviando" @click="iniciar">{{ $t('nivelamento.comecar') }}</BaseButton>
        <BaseButton to="/dashboard" variant="secondary">{{ $t('nivelamento.depois') }}</BaseButton>
      </div>
    </BaseCard>
  </PageContainer>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/shared/utils/intl'
import { BaseButton, BaseCard, BaseProgress, BaseSpinner, PageContainer, PageHeader } from '@/shared/components/ui'
import { nivelamentoService, type NivelamentoEstado, type NivelamentoResultado } from '../services/nivelamentoService'

const { t } = useI18n()
const { error } = useAlert()
const authStore = useAuthStore()

const carregando = ref(true)
const enviando = ref(false)
const estado = ref<NivelamentoEstado | null>(null)
const resultado = ref<NivelamentoResultado | null>(null)
const feedback = ref('')

onMounted(async () => {
  try {
    const { data } = await nivelamentoService.resultado()
    resultado.value = data.data
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    carregando.value = false
  }
})

const iniciar = async () => {
  enviando.value = true
  try {
    const { data } = await nivelamentoService.iniciar()
    estado.value = data.data
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    enviando.value = false
  }
}

const responder = async (alternativa: number) => {
  if (!estado.value || enviando.value) return
  enviando.value = true
  try {
    const { data } = await nivelamentoService.responder(estado.value.id, alternativa)
    feedback.value = data.data.correta ? t('nivelamento.correta') : t('nivelamento.incorreta')
    estado.value = data.data
    if (data.data.concluido && data.data.resultado) {
      resultado.value = data.data.resultado
      // O nível do perfil foi atualizado pelo servidor
      if (authStore.user) authStore.setUser({ ...authStore.user, nivel_ingles: data.data.resultado.nivel_ingles })
    }
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    enviando.value = false
  }
}
</script>

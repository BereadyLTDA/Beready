<template>
  <!-- Aberta pelo link do e-mail de confirmação: /verificar-email/:token -->
  <AuthCard :title="$t('verificarEmail.titulo')" icon="mail">
    <div class="verificar-email flex flex-col items-center gap-4 text-center" role="status" aria-live="polite">
      <BaseSpinner v-if="estado === 'carregando'" :label="$t('verificarEmail.confirmando')" show-label />

      <template v-else-if="estado === 'ok'">
        <p class="text-text">{{ $t('verificarEmail.confirmado') }}</p>
        <BaseButton to="/login" icon="check">{{ $t('verificarEmail.irParaLogin') }}</BaseButton>
      </template>

      <template v-else>
        <p class="text-text">{{ $t('verificarEmail.linkInvalido') }}</p>
        <form class="flex w-full flex-col gap-3" @submit.prevent="reenviar">
          <BaseInput v-model="email" type="email" :label="$t('login.email')" autocomplete="email" required />
          <BaseButton type="submit" :loading="reenviando">{{ $t('verificarEmail.reenviar') }}</BaseButton>
        </form>
      </template>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { BaseButton, BaseInput, BaseSpinner } from '@/shared/components/ui'
import AuthCard from '../components/AuthCard.vue'

const route = useRoute()
const { t } = useI18n()
const { success, error } = useAlert()

const estado = ref<'carregando' | 'ok' | 'erro'>('carregando')
const email = ref('')
const reenviando = ref(false)

onMounted(async () => {
  try {
    await api.post('/auth/verify-email', { token: String(route.params.token ?? '') })
    estado.value = 'ok'
  } catch {
    estado.value = 'erro'
  }
})

const reenviar = async () => {
  reenviando.value = true
  try {
    await api.post('/auth/resend-verification', { email: email.value })
    success(t('verificarEmail.reenviado'))
  } catch (err: unknown) {
    error(getApiErrorMessage(err) || t('errors.networkError'))
  } finally {
    reenviando.value = false
  }
}
</script>

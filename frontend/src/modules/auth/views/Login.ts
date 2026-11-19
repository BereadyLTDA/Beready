import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/shared/composables/useForm'
import { useAlert } from '@/shared/composables/useAlert'
import { useAuthStore } from '@/stores/auth'
import { API_BASE_URL } from '@/shared/config/env'
import { useRecaptcha } from '@/shared/composables/useRecaptcha'
import api, { getApiErrorMessage } from '@/core/services/api'

export function useLogin() {
  const router = useRouter()
  const route = useRoute()
  const authStore = useAuthStore()
  const { success, error, clearAllAlerts } = useAlert()
  const { t } = useI18n()
  const loading = ref(false)

  const { form, errors, validate } = useForm({ email: '', password: '' })

  const rules = {
    email: (value: string) => (!value ? t('register.emailRequired') : null),
    password: (value: string) => {
      if (!value) return t('register.passwordRequired')
      if (value.length < 6) return t('passwordValidation.minLength')
      return null
    },
  }

  const { carregar: loadRecaptcha, gerarToken } = useRecaptcha()
  const getRecaptchaToken = () => gerarToken('login')

  // Senha certa, mas o e-mail ainda não foi confirmado: mostra o reenvio do link
  const precisaConfirmarEmail = ref(false)
  const reenviando = ref(false)
  const reenviarLink = async () => {
    reenviando.value = true
    try {
      await api.post('/auth/resend-verification', { email: form.email })
      success(t('verificarEmail.reenviado'))
    } catch (err: unknown) {
      error(getApiErrorMessage(err) || t('errors.networkError'))
    } finally {
      reenviando.value = false
    }
  }

  const handleSubmit = async () => {
    if (!validate(rules)) return

    loading.value = true

    try {
      let recaptchaToken = ''
      try {
        recaptchaToken = await getRecaptchaToken()
      } catch (err) {
        error((err as Error).message || t('login.recaptchaError'))
        loading.value = false
        return
      }

      const response = await authStore.login({
        email: form.email,
        password: form.password,
        recaptcha_token: recaptchaToken,
      })

      if (response.success) {
        success(t('success.login'))
        setTimeout(() => {
          clearAllAlerts()
          router.push('/dashboard')
        }, 500)
      } else {
        precisaConfirmarEmail.value = response.codigo === 'email_nao_verificado'
        error(response.message || t('login.error'))
      }
    } catch (err) {
      console.error('Erro:', err)
      error(t('errors.networkError'))
    } finally {
      loading.value = false
    }
  }

  // Login social: redireciona a página inteira para o backend, que volta para
  // /oauth-callback?code=... (código de uso único trocado em OAuthCallback.ts)
  const loginWithProvider = (provider: string) => {
    loading.value = true
    window.location.assign(`${API_BASE_URL}/auth/login/${provider}`)
  }

  onMounted(() => {
    loadRecaptcha()

    // Erro devolvido pelo backend no fluxo social (/login?error=social_auth_failed)
    if (route.query.error === 'social_auth_failed') {
      error(t('login.socialAuthFailed'))
      router.replace({ query: {} })
    }
  })

  return {
    form,
    errors,
    loading,
    precisaConfirmarEmail,
    reenviando,
    reenviarLink,
    handleSubmit,
    loginWithProvider,
  }
}

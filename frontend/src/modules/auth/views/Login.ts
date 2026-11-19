import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/shared/composables/useForm'
import { useAlert } from '@/shared/composables/useAlert'
import { useAuthStore } from '@/stores/auth'
import { API_BASE_URL, RECAPTCHA_SITE_KEY, RECAPTCHA_JS_URL } from '@/shared/config/env'

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

  // Carrega o script do reCAPTCHA
  const loadRecaptcha = () => {
    if (!RECAPTCHA_SITE_KEY || !RECAPTCHA_JS_URL) {
      console.warn('VITE_RECAPTCHA_SITE_KEY/VITE_RECAPTCHA_JS_URL não configurados no .env')
      return
    }

    if (document.querySelector('script[src*="recaptcha"]')) {
      return
    }

    const script = document.createElement('script')
    script.src = `${RECAPTCHA_JS_URL}?render=${RECAPTCHA_SITE_KEY}`
    script.async = true
    script.defer = true
    document.head.appendChild(script)
  }

  // Obtém o token do reCAPTCHA
  const getRecaptchaToken = (): Promise<string> => {
    return new Promise((resolve, reject) => {
      const siteKey = RECAPTCHA_SITE_KEY
      if (!siteKey) {
        reject(new Error(t('login.recaptchaNotConfigured')))
        return
      }

      if (typeof window === 'undefined' || !window.grecaptcha) {
        reject(new Error(t('login.recaptchaNotLoaded')))
        return
      }

      // ready(): o script pode ter carregado mas ainda não estar pronto (envio logo ao abrir a página)
      const recaptcha = window.grecaptcha
      recaptcha.ready(() => {
        recaptcha.execute(siteKey, { action: 'login' }).then(resolve).catch(reject)
      })
    })
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
    handleSubmit,
    loginWithProvider,
  }
}

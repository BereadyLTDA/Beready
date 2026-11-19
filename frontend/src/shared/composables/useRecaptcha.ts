import { useI18n } from 'vue-i18n'
import { RECAPTCHA_JS_URL, RECAPTCHA_SITE_KEY } from '@/shared/config/env'

/**
 * reCAPTCHA v3: carrega o script uma vez e gera o token que o backend confere (login e cadastro).
 */
export function useRecaptcha() {
  const { t } = useI18n()

  const carregar = (): void => {
    if (!RECAPTCHA_SITE_KEY || !RECAPTCHA_JS_URL) {
      console.warn('VITE_RECAPTCHA_SITE_KEY/VITE_RECAPTCHA_JS_URL não configurados no .env')
      return
    }
    if (document.querySelector('script[src*="recaptcha"]')) return

    const script = document.createElement('script')
    script.src = `${RECAPTCHA_JS_URL}?render=${RECAPTCHA_SITE_KEY}`
    script.async = true
    script.defer = true
    document.head.appendChild(script)
  }

  const gerarToken = (acao: 'login' | 'register'): Promise<string> =>
    new Promise((resolve, reject) => {
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
        recaptcha.execute(siteKey, { action: acao }).then(resolve).catch(reject)
      })
    })

  return { carregar, gerarToken }
}

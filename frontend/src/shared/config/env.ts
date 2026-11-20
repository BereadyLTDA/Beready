export const API_BASE_URL: string = import.meta.env.VITE_API_URL

if (!API_BASE_URL) {
  throw new Error('VITE_API_URL nao esta definida. Verifique seu arquivo .env')
}

// Opcionais em tempo de import: a tela de login valida antes de usar
export const RECAPTCHA_SITE_KEY: string | undefined = import.meta.env.VITE_RECAPTCHA_SITE_KEY
export const RECAPTCHA_JS_URL: string | undefined = import.meta.env.VITE_RECAPTCHA_JS_URL

// E-mail para pedidos sobre dados pessoais (LGPD), mostrado em /privacidade e /termos.
// Sem ele, as páginas indicam o repositório do projeto como canal de contato.
export const CONTATO_EMAIL: string | undefined = import.meta.env.VITE_CONTATO_EMAIL

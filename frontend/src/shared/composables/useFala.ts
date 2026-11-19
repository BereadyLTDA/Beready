import { onBeforeUnmount, ref } from 'vue'

/**
 * Texto para fala com a voz do próprio navegador (speechSynthesis): sem backend e sem custo.
 */
export function useFala() {
  const suportado = typeof window !== 'undefined' && 'speechSynthesis' in window
  const falando = ref(false)

  // Prefere uma voz do idioma pedido (en-US, en-GB...); sem ela, o navegador escolhe pelo lang
  const vozPara = (lang: string): SpeechSynthesisVoice | undefined => {
    const vozes = window.speechSynthesis.getVoices()
    const base = lang.split('-')[0]
    return vozes.find((v) => v.lang === lang) ?? vozes.find((v) => v.lang.startsWith(base ?? ''))
  }

  const falar = (texto: string, opcoes: { lang?: string; lento?: boolean } = {}): void => {
    if (!suportado || !texto.trim()) return
    const lang = opcoes.lang || 'en-US'
    window.speechSynthesis.cancel()
    const fala = new SpeechSynthesisUtterance(texto)
    fala.lang = lang
    fala.rate = opcoes.lento ? 0.7 : 1
    const voz = vozPara(lang)
    if (voz) fala.voice = voz
    fala.onend = () => (falando.value = false)
    fala.onerror = () => (falando.value = false)
    falando.value = true
    window.speechSynthesis.speak(fala)
  }

  const parar = (): void => {
    if (!suportado) return
    window.speechSynthesis.cancel()
    falando.value = false
  }

  onBeforeUnmount(parar)

  return { suportado, falando, falar, parar }
}

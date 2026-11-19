import { onBeforeUnmount, ref } from 'vue'

// A Web Speech API ainda não tem tipos no lib.dom do TypeScript
interface Reconhecedor {
  lang: string
  interimResults: boolean
  maxAlternatives: number
  start: () => void
  stop: () => void
  abort: () => void
  onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null
  onerror: ((e: { error: string }) => void) | null
  onend: (() => void) | null
}
type ConstrutorReconhecedor = new () => Reconhecedor

/**
 * Reconhecimento de voz do navegador (Chrome e Edge). Em outros navegadores, suportado = false.
 * Exige HTTPS (ou localhost) e a permissão do microfone.
 */
export function useReconhecimentoVoz() {
  const w = typeof window !== 'undefined' ? (window as unknown as Record<string, unknown>) : {}
  const Construtor = (w.SpeechRecognition ?? w.webkitSpeechRecognition) as ConstrutorReconhecedor | undefined
  const suportado = !!Construtor

  const ouvindo = ref(false)
  const erro = ref<string | null>(null)
  let atual: Reconhecedor | null = null

  /** Ouve uma frase e devolve o texto reconhecido ('' se nada foi entendido). */
  const ouvir = (lang = 'en-US'): Promise<string> =>
    new Promise((resolve, reject) => {
      if (!Construtor) {
        reject(new Error('nao-suportado'))
        return
      }
      atual?.abort()
      const r = new Construtor()
      atual = r
      r.lang = lang
      r.interimResults = false
      r.maxAlternatives = 1
      let texto = ''
      erro.value = null
      r.onresult = (e) => {
        texto = e.results[0]?.[0]?.transcript ?? ''
      }
      r.onerror = (e) => {
        // "no-speech" não é falha: a pessoa só não falou nada
        if (e.error !== 'no-speech' && e.error !== 'aborted') erro.value = e.error
      }
      r.onend = () => {
        ouvindo.value = false
        atual = null
        if (erro.value) reject(new Error(erro.value))
        else resolve(texto)
      }
      ouvindo.value = true
      r.start()
    })

  const parar = (): void => atual?.stop()

  onBeforeUnmount(() => atual?.abort())

  return { suportado, ouvindo, erro, ouvir, parar }
}

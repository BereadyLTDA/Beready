import api from '@/core/services/api'
import type { components } from '@/core/types/api.gen'

export type FrasePronuncia = components['schemas']['FrasePronuncia']
export type PronunciaResultado = components['schemas']['PronunciaResultado']
export type PronunciaResumo = components['schemas']['PronunciaResumo']

interface Resposta<T> {
  success: boolean
  message: string
  data: T
}

export const pronunciaService = {
  frases(limite = 10) {
    return api.get<Resposta<FrasePronuncia[]>>('/pronuncia/frases', { params: { limite } })
  },
  // A nota é calculada pelo servidor a partir do que o reconhecimento de voz entendeu
  registrar(frase: string, reconhecido: string) {
    return api.post<Resposta<PronunciaResultado>>('/pronuncia/tentativas', { frase, reconhecido })
  },
  resumo() {
    return api.get<Resposta<PronunciaResumo>>('/pronuncia/resumo')
  },
}

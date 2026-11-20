import api from '@/core/services/api'
import type { components } from '@/core/types/api.gen'

export type NivelamentoEstado = components['schemas']['NivelamentoEstado']
export type NivelamentoResultado = components['schemas']['NivelamentoResultado']

interface Resposta<T> {
  success: boolean
  message: string
  data: T
}

export const nivelamentoService = {
  // Começa ou continua o teste; a correção é toda feita no servidor
  iniciar() {
    return api.post<Resposta<NivelamentoEstado>>('/nivelamento/iniciar')
  },
  responder(id: number, alternativa: number) {
    return api.post<Resposta<NivelamentoEstado & { correta: boolean }>>(`/nivelamento/${id}/responder`, { alternativa })
  },
  resultado() {
    return api.get<Resposta<NivelamentoResultado | null>>('/nivelamento/resultado')
  },
}

import api from '@/core/services/api'
import type { components } from '@/core/types/api.gen'

export type Conquista = components['schemas']['Conquista']
export type MetaDiaria = components['schemas']['MetaDiaria']

interface Resposta<T> {
  success: boolean
  message: string
  data: T
}

export const conquistaService = {
  // As regras são conferidas no servidor a cada consulta
  listar() {
    return api.get<Resposta<Conquista[]>>('/conquistas')
  },
  marcarVistas() {
    return api.post<Resposta<null>>('/conquistas/vistas')
  },
  metaDiaria() {
    return api.get<Resposta<MetaDiaria>>('/meta-diaria')
  },
}

import api from '@/core/services/api'
import type { Preferencia, ApiResponse } from '@/core/types'

export const preferenciaService = {
  getByUsuario: (usuarioId: number): Promise<ApiResponse<Preferencia>> => {
    return api.get(`/preferencias/usuario/${usuarioId}`)
  },

  save: (data: Omit<Preferencia, 'id'>): Promise<ApiResponse<Preferencia>> => {
    return api.post('/preferencias', data)
  },
}

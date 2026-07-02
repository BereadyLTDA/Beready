import api from '@/core/services/api'
import type { Preferencia, ApiResponse } from '@/core/types'

export const preferenciaService = {
  getByUsuario: (usuarioId: number) => {
    return api.get(`/preferencias/usuario/${usuarioId}`)
  },

  save: (data: Omit<Preferencia, 'id'>) => {
    return api.post('/preferencias', data)
  },
}

import api from '@/core/services/api'
import type { ApiResponse, Progresso } from '@/core/types'

export type { Progresso }

export const progressoService = {
  // Progresso salvo + taxa_acerto e progresso_geral calculados pelo backend
  getByUsuario(usuarioId: number) {
    return api.get<ApiResponse<Progresso>>(`/progresso/usuario/${usuarioId}`)
  },

  save(data: Progresso) {
    return api.post<ApiResponse<Progresso>>('/progresso', data)
  },

  // tempo_total_estudo é acumulado em segundos
  incrementarTempo(segundos: number, usuario_id: number) {
    return api.post<ApiResponse<Progresso>>('/progresso/incrementar-tempo', {
      usuario_id,
      segundos,
    })
  },
}

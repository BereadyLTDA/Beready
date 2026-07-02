import api from '@/core/services/api'
import type { Frase, ApiResponse } from '@/core/types'

export const fraseService = {
  getByPrompt: (promptId: number): Promise<ApiResponse<Frase[]>> =>
    api.get(`/frases/prompt/${promptId}`),

  getById: (id: number): Promise<ApiResponse<Frase>> => api.get(`/frases/view/${id}`),

  create: (data: Omit<Frase, 'id' | 'criado_em'>): Promise<ApiResponse<Frase>> =>
    api.post('/frases', data),

  update: (
    id: number,
    data: Partial<Omit<Frase, 'id' | 'criado_em'>>,
  ): Promise<ApiResponse<Frase>> => api.put(`/frases/edit/${id}`, data),

  delete: (id: number): Promise<ApiResponse<null>> => api.delete(`/frases/delete/${id}`),
}

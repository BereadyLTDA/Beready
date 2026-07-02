import api from '@/core/services/api'
import type { Traducao, ApiResponse } from '@/core/types'

export const traducaoService = {
  getByPrompt: (promptId: number): Promise<ApiResponse<Traducao[]>> =>
    api.get(`/traducoes/prompt/${promptId}`),

  getById: (id: number): Promise<ApiResponse<Traducao>> => api.get(`/traducoes/view/${id}`),

  create: (data: Omit<Traducao, 'id' | 'criado_em'>): Promise<ApiResponse<Traducao>> =>
    api.post('/traducoes', data),

  update: (
    id: number,
    data: Partial<Omit<Traducao, 'id' | 'criado_em'>>,
  ): Promise<ApiResponse<Traducao>> => api.put(`/traducoes/edit/${id}`, data),

  delete: (id: number): Promise<ApiResponse<null>> => api.delete(`/traducoes/delete/${id}`),
}

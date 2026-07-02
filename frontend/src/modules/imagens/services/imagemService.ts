import api from '@/core/services/api'
import type { Imagem, ApiResponse } from '@/core/types'

export const imagemService = {
  getByPrompt: (promptId: number): Promise<ApiResponse<Imagem[]>> =>
    api.get(`/imagens/prompt/${promptId}`),

  getById: (id: number): Promise<ApiResponse<Imagem>> => api.get(`/imagens/view/${id}`),

  create: (data: Omit<Imagem, 'id' | 'criado_em'>): Promise<ApiResponse<Imagem>> =>
    api.post('/imagens', data),

  update: (
    id: number,
    data: Partial<Omit<Imagem, 'id' | 'criado_em'>>,
  ): Promise<ApiResponse<Imagem>> => api.put(`/imagens/edit/${id}`, data),

  delete: (id: number): Promise<ApiResponse<null>> => api.delete(`/imagens/delete/${id}`),
}

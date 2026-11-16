import api from '@/core/services/api'
import type { Traducao, ApiResponse } from '@/core/types'

type TraducaoInput = Omit<Traducao, 'id' | 'criado_em'>

// Todos os métodos devolvem o corpo da resposta ({ success, message, data })
export const traducaoService = {
  getByPrompt: async (promptId: number): Promise<ApiResponse<Traducao[]>> =>
    (await api.get<ApiResponse<Traducao[]>>(`/traducoes/prompt/${promptId}`)).data,

  getById: async (id: number): Promise<ApiResponse<Traducao>> =>
    (await api.get<ApiResponse<Traducao>>(`/traducoes/view/${id}`)).data,

  create: async (data: TraducaoInput): Promise<ApiResponse<Traducao>> =>
    (await api.post<ApiResponse<Traducao>>('/traducoes', data)).data,

  update: async (id: number, data: Partial<TraducaoInput>): Promise<ApiResponse<Traducao>> =>
    (await api.put<ApiResponse<Traducao>>(`/traducoes/edit/${id}`, data)).data,

  // Traduz o texto do prompt com IA (o backend cria e devolve a tradução)
  gerar: async (promptId: number, idiomaDestino: string): Promise<ApiResponse<Traducao>> =>
    (
      await api.post<ApiResponse<Traducao>>(`/prompts/${promptId}/traducoes/gerar`, {
        idioma_destino: idiomaDestino,
      })
    ).data,

  delete: async (id: number): Promise<ApiResponse<null>> =>
    (await api.delete<ApiResponse<null>>(`/traducoes/delete/${id}`)).data,
}

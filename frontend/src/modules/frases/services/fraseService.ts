import api from '@/core/services/api'
import type { Frase, ApiResponse } from '@/core/types'

type FraseInput = Omit<Frase, 'id' | 'criado_em'>

// Todos os métodos devolvem o corpo da resposta ({ success, message, data })
export const fraseService = {
  getByPrompt: async (promptId: number): Promise<ApiResponse<Frase[]>> =>
    (await api.get<ApiResponse<Frase[]>>(`/frases/prompt/${promptId}`)).data,

  getById: async (id: number): Promise<ApiResponse<Frase>> =>
    (await api.get<ApiResponse<Frase>>(`/frases/view/${id}`)).data,

  create: async (data: FraseInput): Promise<ApiResponse<Frase>> =>
    (await api.post<ApiResponse<Frase>>('/frases', data)).data,

  update: async (id: number, data: Partial<FraseInput>): Promise<ApiResponse<Frase>> =>
    (await api.put<ApiResponse<Frase>>(`/frases/edit/${id}`, data)).data,

  // Gera frases semelhantes ao texto do prompt com IA (o backend cria e devolve as frases)
  gerar: async (promptId: number, quantidade?: number): Promise<ApiResponse<Frase[]>> =>
    (
      await api.post<ApiResponse<Frase[]>>(
        `/prompts/${promptId}/frases/gerar`,
        quantidade ? { quantidade } : {},
      )
    ).data,

  delete: async (id: number): Promise<ApiResponse<null>> =>
    (await api.delete<ApiResponse<null>>(`/frases/delete/${id}`)).data,
}

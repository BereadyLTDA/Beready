import api from '@/core/services/api'

export interface Progresso {
  id?: number
  usuario_id: number
  vocabulario_aprendido?: number
  flashcards_concluidos?: number
  quizes_concluidos?: number
  tempo_total_estudo?: number
  sequencia_atual?: number
  maior_sequencia?: number
  ultima_atividade?: string
  progresso_nivel?: any
  // Ainda não implementados no backend (ver plano de implementação).
  // Quando o backend passar a retornar esses campos, o front já vai
  // reconhecê-los automaticamente sem precisar de outra alteração aqui.
  taxa_acerto?: number
  progresso_geral?: number
}

export const progressoService = {
  getByUsuario: (usuarioId: number) => api.get(`/progresso/usuario/${usuarioId}`),
  save: (data: Progresso) => api.post('/progresso', data),
}
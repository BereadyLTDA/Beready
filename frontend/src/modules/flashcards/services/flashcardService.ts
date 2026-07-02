import api from '@/core/services/api'

export interface Flashcard {
  id?: number
  usuario_id: number
  prompt_id?: number
  frase_id?: number
  pergunta: string
  resposta: string
  dificuldade: 'facil' | 'medio' | 'dificil'
  ultima_revisao?: string
  proxima_revisao?: string
  intervalo_dias?: number
  fator_ease?: number
  repeticoes?: number
  criado_em?: string
  atualizado_em?: string
}

export const flashcardService = {
  // Listar flashcards do usuário
  getByUsuario: (usuarioId: number) => {
    return api.get(`/flashcards?usuario_id=${usuarioId}`)
  },

  // Buscar flashcard por ID
  getById: (id: number) => {
    return api.get(`/flashcards/view/${id}`)
  },

  // Buscar flashcards por prompt
  getByPrompt: (promptId: number) => {
    return api.get(`/flashcards?prompt_id=${promptId}`)
  },

  // Criar flashcard
  create: (data: Omit<Flashcard, 'id' | 'criado_em' | 'atualizado_em'>) => {
    return api.post('/flashcards', data)
  },

  // Atualizar flashcard
  update: (id: number, data: Partial<Flashcard>) => {
    return api.put(`/flashcards/edit/${id}`, data)
  },

  // Deletar flashcard
  delete: (id: number) => {
    return api.delete(`/flashcards/delete/${id}`)
  },

  // Atualizar programação de revisão (SRS)
  atualizarRevisao: (id: number, qualidade: number) => {
    return api.post(`/flashcards/${id}/revisao`, { qualidade })
  },
}

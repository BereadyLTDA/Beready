import api from '@/core/services/api'
export interface FlashcardTag {
  id?: number
  flashcard_id: number
  tag_id: number
}

export const flashcardTagService = {
  // Listar tags de um flashcard
  getByFlashcard: (flashcardId: number) => api.get(`/flashcard-tags/flashcard/${flashcardId}`),

  // Adicionar tag ao flashcard
  add: (flashcardId: number, tagId: number) =>
    api.post('/flashcard-tags', { flashcard_id: flashcardId, tag_id: tagId }),

  // Remover tag do flashcard
  remove: (flashcardId: number, tagId: number) =>
    api.delete('/flashcard-tags', { data: { flashcard_id: flashcardId, tag_id: tagId } }),
}

import { ref, onMounted } from 'vue'
import { flashcardService } from '../services/flashcardService'
import { useAlert } from '@/shared/composables/useAlert'

export function useFlashcards() {
  const flashcards = ref<any[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()

  const loadFlashcards = async (usuarioId: number) => {
    loading.value = true
    try {
      const response = await flashcardService.getByUsuario(usuarioId)
      flashcards.value = response.data.data || []
      return flashcards.value
    } catch (err: any) {
      console.error('Erro ao carregar flashcards:', err)
      error('Erro ao carregar flashcards')
      flashcards.value = []
    } finally {
      loading.value = false
    }
  }

  const createFlashcard = async (data: any) => {
    loading.value = true
    try {
      const response = await flashcardService.create(data)
      success('Flashcard criado com sucesso!')
      return response.data.data
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao criar flashcard')
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateFlashcard = async (id: number, data: any) => {
    loading.value = true
    try {
      const response = await flashcardService.update(id, data)
      success('Flashcard atualizado com sucesso!')
      return response.data.data
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao atualizar flashcard')
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteFlashcard = async (id: number) => {
    loading.value = true
    try {
      await flashcardService.delete(id)
      success('Flashcard excluído com sucesso!')
      return true
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao excluir flashcard')
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    flashcards,
    loading,
    loadFlashcards,
    createFlashcard,
    updateFlashcard,
    deleteFlashcard,
  }
}

import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useFlashcardView() {
  const router = useRouter()
  const route = useRoute()
  const { success, error } = useAlert()
  const flashcard = ref<any>(null)
  const loading = ref(true)
  const deleting = ref(false)
  const showConfirmModal = ref(false)

  const loadFlashcard = async () => {
    const id = route.params.id
    if (!id) {
      error('ID do flashcard não informado')
      router.push('/flashcards')
      return
    }

    loading.value = true
    try {
      const response = await fetch(`${API_BASE_URL}/flashcards/${id}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      const data = await response.json()

      if (data.success) {
        flashcard.value = data.data
      } else {
        error(data.message || 'Erro ao carregar flashcard')
        router.push('/flashcards')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
      router.push('/flashcards')
    } finally {
      loading.value = false
    }
  }

  const goBack = () => {
    router.push('/flashcards')
  }

  const editFlashcard = () => {
    router.push(`/flashcards/${flashcard.value.id}/edit`)
  }

  const studyFlashcard = () => {
    router.push(`/flashcards/${flashcard.value.id}/study`)
  }

  const openDeleteModal = () => {
    showConfirmModal.value = true
  }

  const confirmDelete = async () => {
    if (!flashcard.value?.id) return

    deleting.value = true
    try {
      const response = await fetch(`${API_BASE_URL}/flashcards/${flashcard.value.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      const data = await response.json()

      if (data.success) {
        success('Flashcard excluído com sucesso!')
        showConfirmModal.value = false
        router.push('/flashcards')
      } else {
        error(data.message || 'Erro ao excluir flashcard')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
    } finally {
      deleting.value = false
    }
  }

  const getLevelClass = (level: string) => {
    const classes: Record<string, string> = {
      iniciante: 'level-beginner',
      intermediario: 'level-intermediate',
      avancado: 'level-advanced',
    }
    return classes[level] || 'level-beginner'
  }

  const getLevelText = (level: string) => {
    const texts: Record<string, string> = {
      iniciante: 'Iniciante',
      intermediario: 'Intermediário',
      avancado: 'Avançado',
    }
    return texts[level] || level
  }

  const formatDate = (date: string) => {
    if (!date) return 'Data não informada'
    return new Date(date).toLocaleDateString('pt-BR')
  }

  onMounted(() => {
    loadFlashcard()
  })

  return {
    flashcard,
    loading,
    deleting,
    showConfirmModal,
    goBack,
    editFlashcard,
    studyFlashcard,
    confirmDelete,
    getLevelClass,
    getLevelText,
    formatDate,
  }
}

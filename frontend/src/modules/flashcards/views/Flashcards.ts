import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

interface Flashcard {
  id: number
  usuario_id: number
  frente: string
  verso: string
  nivel_dificuldade: string
  criado_em?: string
  atualizado_em?: string
}

interface NewFlashcard {
  frente: string
  verso: string
  nivel_dificuldade: string
}

export function useFlashcards() {
  const router = useRouter()
  const { success, error } = useAlert()
  const flashcards = ref<Flashcard[]>([])
  const loading = ref(true)
  const saving = ref(false)
  const deleting = ref(false)
  const showModal = ref(false)
  const showConfirmModal = ref(false)
  const isEditing = ref(false)
  const editingId = ref<number | null>(null)
  const flashcardToDeleteId = ref<number | null>(null)
  const flashcardToDeleteTitle = ref('')

  const form = ref<NewFlashcard>({
    frente: '',
    verso: '',
    nivel_dificuldade: 'iniciante',
  })

  const loadFlashcards = async () => {
    loading.value = true
    try {
      const response = await fetch('${API_BASE_URL}/flashcards', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      const data = await response.json()

      if (data.success) {
        flashcards.value = data.data || []
      } else {
        error(data.message || 'Erro ao carregar flashcards')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  const viewFlashcard = (id: number) => {
    router.push(`/flashcards/${id}`)
  }

  const studyFlashcard = (id: number) => {
    router.push(`/flashcards/${id}/study`)
  }

  const editFlashcard = (flashcard: Flashcard) => {
    isEditing.value = true
    editingId.value = flashcard.id
    form.value = {
      frente: flashcard.frente,
      verso: flashcard.verso,
      nivel_dificuldade: flashcard.nivel_dificuldade || 'iniciante',
    }
    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    isEditing.value = false
    editingId.value = null
    form.value = {
      frente: '',
      verso: '',
      nivel_dificuldade: 'iniciante',
    }
  }

  const saveFlashcard = async () => {

    if (!form.value.frente) {
      error('A pergunta é obrigatória')
      return
    }

    if (!form.value.verso) {
      error('A resposta é obrigatória')
      return
    }

    const userData = localStorage.getItem('user')
    if (!userData) {
      error('Usuário não autenticado')
      router.push('/login')
      return
    }

    const user = JSON.parse(userData)

    saving.value = true
    try {
      const url = isEditing.value
        ? `${API_BASE_URL}/flashcards/${editingId.value}`
        : '${API_BASE_URL}/flashcards'

      const method = isEditing.value ? 'PUT' : 'POST'

      const requestBody = {
        frente: form.value.frente,
        verso: form.value.verso,
        nivel_dificuldade: form.value.nivel_dificuldade,
        usuario_id: user.id,
      }

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(requestBody),
      })

      const data = await response.json()

      if (data.success) {
        success(
          isEditing.value ? 'Flashcard atualizado com sucesso!' : 'Flashcard criado com sucesso!',
        )
        closeModal()
        loadFlashcards()
      } else {
        error(data.message || 'Erro ao salvar flashcard')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
    } finally {
      saving.value = false
    }
  }

  const openDeleteModal = (id: number, frente: string) => {
    flashcardToDeleteId.value = id
    flashcardToDeleteTitle.value = frente.substring(0, 50) + (frente.length > 50 ? '...' : '')
    showConfirmModal.value = true
  }

  const confirmDelete = async () => {
    if (!flashcardToDeleteId.value) return

    deleting.value = true
    try {
      const response = await fetch(
        `${API_BASE_URL}/flashcards/${flashcardToDeleteId.value}`,
        {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
        },
      )

      const data = await response.json()

      if (data.success) {
        success('Flashcard excluído com sucesso!')
        showConfirmModal.value = false
        flashcardToDeleteId.value = null
        flashcardToDeleteTitle.value = ''
        loadFlashcards()
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

  const openCreateModal = () => {
    isEditing.value = false
    editingId.value = null
    form.value = { frente: '', verso: '', nivel_dificuldade: 'iniciante' }
    showModal.value = true
  }

  const formatDate = (date: string | undefined): string => {
    if (!date) return 'Data não informada'
    return new Date(date).toLocaleDateString('pt-BR')
  }

  onMounted(() => {
    loadFlashcards()
  })

  return {
    flashcards,
    loading,
    saving,
    deleting,
    showModal,
    showConfirmModal,
    isEditing,
    editingId,
    form,
    flashcardToDeleteTitle,
    viewFlashcard,
    studyFlashcard,
    editFlashcard,
    saveFlashcard,
    closeModal,
    openDeleteModal,
    confirmDelete,
    openCreateModal,
    formatDate,
  }
}

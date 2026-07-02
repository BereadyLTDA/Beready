import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import api from '@/core/services/api'
import { AxiosError } from 'axios'

// Definição de tipos
interface Quiz {
  id: number
  titulo: string
  descricao: string
  nivel_dificuldade: string
  total_questoes: number
  tempo_limite: number | null
  publico: boolean
  criado_em?: string
  atualizado_em?: string
}

interface NewQuiz {
  titulo: string
  descricao: string
  nivel_dificuldade: string
  total_questoes: number
  tempo_limite: number | null
  publico: boolean
}

interface EditForm extends NewQuiz {
  id: number | null
}

interface ApiErrorResponse {
  message?: string
  success?: boolean
}

export function useQuizes() {
  const router = useRouter()
  const { success, error } = useAlert()
  const quizes = ref<Quiz[]>([])
  const loading = ref(true)
  const creating = ref(false)
  const deleting = ref(false)
  const editing = ref(false)
  const showCreateModal = ref(false)
  const showEditModal = ref(false)
  const showConfirmModal = ref(false)
  const quizToDeleteId = ref<number | null>(null)
  const quizToDeleteTitle = ref('')
  const selectedQuiz = ref<Quiz | null>(null)

  const newQuiz = ref<NewQuiz>({
    titulo: '',
    descricao: '',
    nivel_dificuldade: 'iniciante',
    total_questoes: 0,
    tempo_limite: null,
    publico: false,
  })

  const editForm = ref<EditForm>({
    id: null,
    titulo: '',
    descricao: '',
    nivel_dificuldade: 'iniciante',
    total_questoes: 0,
    tempo_limite: null,
    publico: false,
  })

  const addQuiz = () => {
    showCreateModal.value = true
  }

  const loadQuizes = async () => {
    loading.value = true
    try {
      const response = await api.get('/quizes')

      if (response.data.success) {
        quizes.value = response.data.data
      } else {
        error(response.data.message || 'Erro ao carregar quizzes')
      }
    } catch (err: unknown) {
      console.error('Erro ao carregar quizzes:', err)

      if (err instanceof AxiosError) {
        const errorData = err.response?.data as ApiErrorResponse
        if (err.response?.status === 404) {
          error('Rota não encontrada. Verifique se o servidor está rodando.')
        } else if (err.response?.status === 500) {
          error('Erro interno do servidor.')
        } else {
          error(errorData?.message || 'Erro ao carregar quizzes')
        }
      } else if (err instanceof Error) {
        error('Erro de conexão com o servidor: ' + err.message)
      } else {
        error('Erro desconhecido ao carregar quizzes')
      }
    } finally {
      loading.value = false
    }
  }

  const viewQuiz = (id: number) => {
    router.push(`/quizes/${id}`)
  }

  const startQuiz = (id: number) => {
    router.push(`/quizes/${id}/play`)
  }

  const editQuiz = (quiz: Quiz) => {
    selectedQuiz.value = quiz
    editForm.value = {
      id: quiz.id,
      titulo: quiz.titulo,
      descricao: quiz.descricao || '',
      nivel_dificuldade: quiz.nivel_dificuldade || 'iniciante',
      total_questoes: quiz.total_questoes || 0,
      tempo_limite: quiz.tempo_limite || null,
      publico: quiz.publico || false,
    }
    showEditModal.value = true
  }

  const updateQuiz = async () => {
    if (!editForm.value.titulo) {
      error('Título é obrigatório')
      return
    }

    editing.value = true
    try {
      const response = await api.put(`/quizes/${editForm.value.id}`, {
        titulo: editForm.value.titulo,
        descricao: editForm.value.descricao,
        nivel_dificuldade: editForm.value.nivel_dificuldade,
        total_questoes: editForm.value.total_questoes,
        tempo_limite: editForm.value.tempo_limite,
        publico: editForm.value.publico,
      })

      if (response.data.success) {
        success('Quiz atualizado com sucesso!')
        showEditModal.value = false
        await loadQuizes()
      } else {
        error(response.data.message || 'Erro ao atualizar quiz')
      }
    } catch (err: unknown) {
      console.error('Erro ao atualizar quiz:', err)

      if (err instanceof AxiosError) {
        const errorData = err.response?.data as ApiErrorResponse
        error(errorData?.message || 'Erro de conexão com o servidor')
      } else if (err instanceof Error) {
        error(err.message)
      } else {
        error('Erro desconhecido ao atualizar quiz')
      }
    } finally {
      editing.value = false
    }
  }

  const openDeleteModal = (id: number, titulo: string) => {
    quizToDeleteId.value = id
    quizToDeleteTitle.value = titulo
    showConfirmModal.value = true
  }

  const confirmDelete = async () => {
    if (!quizToDeleteId.value) return

    deleting.value = true
    try {
      const response = await api.delete(`/quizes/${quizToDeleteId.value}`)

      if (response.data.success) {
        success('Quiz excluído com sucesso!')
        showConfirmModal.value = false
        quizToDeleteId.value = null
        quizToDeleteTitle.value = ''
        await loadQuizes()
      } else {
        error(response.data.message || 'Erro ao excluir quiz')
      }
    } catch (err: unknown) {
      console.error('Erro ao excluir quiz:', err)

      if (err instanceof AxiosError) {
        const errorData = err.response?.data as ApiErrorResponse
        error(errorData?.message || 'Erro de conexão com o servidor')
      } else if (err instanceof Error) {
        error(err.message)
      } else {
        error('Erro desconhecido ao excluir quiz')
      }
    } finally {
      deleting.value = false
    }
  }

  const createQuiz = async () => {
    if (!newQuiz.value.titulo) {
      error('Título é obrigatório')
      return
    }

    const userData = localStorage.getItem('user')
    if (!userData) {
      error('Usuário não autenticado')
      router.push('/login')
      return
    }

    const user = JSON.parse(userData) as { id: number }

    creating.value = true
    try {
      const response = await api.post('/quizes', {
        ...newQuiz.value,
        usuario_id: user.id,
      })

      if (response.data.success) {
        success('Quiz criado com sucesso!')
        showCreateModal.value = false
        newQuiz.value = {
          titulo: '',
          descricao: '',
          nivel_dificuldade: 'iniciante',
          total_questoes: 0,
          tempo_limite: null,
          publico: false,
        }
        await loadQuizes()
      } else {
        error(response.data.message || 'Erro ao criar quiz')
      }
    } catch (err: unknown) {
      console.error('Erro ao criar quiz:', err)

      if (err instanceof AxiosError) {
        const errorData = err.response?.data as ApiErrorResponse

        if (err.response?.status === 401) {
          error('Sessão expirada. Faça login novamente.')
          router.push('/login')
        } else {
          error(errorData?.message || 'Erro de conexão com o servidor')
        }
      } else if (err instanceof Error) {
        error(err.message)
      } else {
        error('Erro desconhecido ao criar quiz')
      }
    } finally {
      creating.value = false
    }
  }

  const getLevelClass = (level: string): string => {
    const classes: Record<string, string> = {
      iniciante: 'level-beginner',
      intermediario: 'level-intermediate',
      avancado: 'level-advanced',
    }
    return classes[level] || 'level-beginner'
  }

  const getLevelText = (level: string): string => {
    const texts: Record<string, string> = {
      iniciante: 'Iniciante',
      intermediario: 'Intermediário',
      avancado: 'Avançado',
    }
    return texts[level] || level
  }

  const formatDate = (date: string | undefined): string => {
    if (!date) return 'Data não informada'
    return new Date(date).toLocaleDateString('pt-BR')
  }

  onMounted(() => {
    loadQuizes()
  })

  return {
    quizes,
    loading,
    creating,
    deleting,
    editing,
    showCreateModal,
    showEditModal,
    showConfirmModal,
    newQuiz,
    editForm,
    quizToDeleteId,
    quizToDeleteTitle,
    viewQuiz,
    startQuiz,
    addQuiz,
    editQuiz,
    updateQuiz,
    openDeleteModal,
    confirmDelete,
    createQuiz,
    getLevelClass,
    getLevelText,
    formatDate,
    loadQuizes,
  }
}

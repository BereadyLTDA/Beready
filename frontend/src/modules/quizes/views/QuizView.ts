import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useQuizView() {
  const router = useRouter()
  const route = useRoute()
  const { error } = useAlert()
  const quizId = ref<number | null>(null)
  const quiz = ref({
    id: null,
    titulo: '',
    descricao: '',
    nivel_dificuldade: '',
    total_questoes: 0,
    tempo_limite: null,
    publico: false,
    criado_em: null,
  })

  const loadQuiz = async () => {
    const id = route.params.id
    if (!id) {
      error('ID do quiz não informado')
      router.push('/quizes')
      return
    }

    quizId.value = Number(id)

    try {
      const response = await fetch(`${API_BASE_URL}quizes/${quizId.value}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      const data = await response.json()

      if (data.success) {
        quiz.value = data.data
      } else {
        error(data.message || 'Erro ao carregar quiz')
        router.push('/quizes')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
      router.push('/quizes')
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

  const formatDate = (date: string | null) => {
    if (!date) return 'Não informado'
    return new Date(date).toLocaleDateString('pt-BR')
  }

  onMounted(() => {
    loadQuiz()
  })

  return {
    quiz,
    quizId,
    getLevelClass,
    getLevelText,
    formatDate,
  }
}

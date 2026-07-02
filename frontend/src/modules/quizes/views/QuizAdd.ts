import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useQuizAdd() {
  const router = useRouter()
  const { success, error } = useAlert()
  const loading = ref(false)

  const form = ref({
    titulo: '',
    descricao: '',
    tipo_criacao: 'manual',
    nivel_dificuldade: 'iniciante',
    total_questoes: 0,
    tempo_limite: null,
    publico: false,
  })

  const errors = ref({
    titulo: '',
  })

  const handleSubmit = async () => {
    // Validação
    if (!form.value.titulo) {
      errors.value.titulo = 'Título é obrigatório'
      return
    }

    const userData = localStorage.getItem('user')
    if (!userData) {
      error('Usuário não autenticado')
      router.push('/login')
      return
    }

    const user = JSON.parse(userData)
    loading.value = true

    try {
      const response = await fetch('${API_BASE_URL}/quizes', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          ...form.value,
          usuario_id: user.id,
        }),
      })

      const data = await response.json()

      if (data.success) {
        success('Quiz criado com sucesso!')
        setTimeout(() => router.push('/quizes'), 1500)
      } else {
        error(data.message || 'Erro ao criar quiz')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  return {
    form,
    errors,
    loading,
    handleSubmit,
  }
}

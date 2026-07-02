import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useForgotPassword() {
  const router = useRouter()
  const { success, error } = useAlert()
  const loading = ref(false)

  const form = ref({ email: '' })

  const handleSubmit = async () => {
    if (!form.value.email) {
      error('E-mail é obrigatório')
      return
    }

    loading.value = true

    try {
      const response = await fetch('${API_BASE_URL}/auth/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: form.value.email }),
      })
      const data = await response.json()

      if (data.success) {
        success('Link de recuperação enviado para seu e-mail!')
        setTimeout(() => router.push('/login'), 2000)
      } else {
        error(data.message || 'Erro ao enviar link')
      }
    } catch (err) {
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  return { form, loading, handleSubmit }
}

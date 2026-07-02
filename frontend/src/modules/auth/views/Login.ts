import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForm } from '@/shared/composables/useForm'
import { useAlert } from '@/shared/composables/useAlert'
import { auth } from '@/core/services/api'

export function useLogin() {
  const router = useRouter()
  const { success, error, clearAllAlerts } = useAlert()
  const loading = ref(false)

  const { form, errors, validate } = useForm({ email: '', password: '' })

  const rules = {
    email: (value: string) => (!value ? 'E-mail é obrigatório' : null),
    password: (value: string) => {
      if (!value) return 'Senha é obrigatória'
      if (value.length < 6) return 'A senha deve ter pelo menos 6 caracteres'
      return null
    },
  }

  const handleSubmit = async () => {
    if (!validate(rules)) return

    loading.value = true

    try {
      const response = await auth.login({
        email: form.email,
        password: form.password,
      })

      if (response.success) {
        success('Login realizado com sucesso!')
        setTimeout(() => {
          clearAllAlerts()
          router.push('/dashboard')
        }, 500)
      } else {
        error(response.message || 'E-mail ou senha inválidos')
      }
    } catch (err) {
      console.error(' Erro:', err)
      error('Erro de conexão com o servidor. Verifique se o backend está rodando.')
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

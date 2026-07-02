import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useResetPassword() {
  const router = useRouter()
  const route = useRoute()
  const { success, error } = useAlert()
  const loading = ref(false)
  const showPassword = ref(false)
  const showConfirmPassword = ref(false)

  const form = ref({
    senha: '',
    confirmar_senha: '',
    token: '',
  })

  const handleSubmit = async () => {
    if (!form.value.senha) {
      error('Nova senha é obrigatória')
      return
    }
    if (form.value.senha.length < 6) {
      error('A senha deve ter pelo menos 6 caracteres')
      return
    }
    if (form.value.senha !== form.value.confirmar_senha) {
      error('As senhas não coincidem')
      return
    }

    loading.value = true

    try {
      const response = await fetch(
        `${API_BASE_URL}/auth/reset-password/${form.value.token}`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ senha: form.value.senha }),
        },
      )
      const data = await response.json()

      if (data.success) {
        success('Senha redefinida com sucesso! Redirecionando...')
        setTimeout(() => router.push('/login'), 2000)
      } else {
        error(data.message || 'Erro ao redefinir senha')
      }
    } catch (err) {
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    form.value.token = (route.params.token as string) || (route.query.token as string)
    if (!form.value.token) {
      error('Token inválido ou expirado')
    }
  })

  return {
    form,
    loading,
    showPassword,
    showConfirmPassword,
    handleSubmit,
  }
}

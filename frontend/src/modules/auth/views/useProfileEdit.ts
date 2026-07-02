import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { API_BASE_URL } from '@/shared/config/env'

export function useProfileEdit() {
  const router = useRouter()
  const { success, error } = useAlert()
  const loading = ref(false)
  const showPassword = ref(false)
  const showConfirmPassword = ref(false)
  const userId = ref<number | null>(null)

  const form = ref({
    nome: '',
    email: '',
    telefone: '',
    nivel_ingles: '',
    idioma_preferido: '',
    status: 'ativo',
    objetivos_aprendizado: '',
    nova_senha: '',
    confirmar_senha: '',
  })

  const formatPhone = (phone: string) => {
    if (!phone) return ''
    const digits = phone.replace(/\D/g, '')
    if (digits.length === 11) {
      return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1)$2-$3')
    }
    if (digits.length === 10) {
      return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1)$2-$3')
    }
    return phone
  }

  const loadUserData = async () => {
    const userData = localStorage.getItem('user')
    if (!userData) {
      router.push('/login')
      return
    }

    try {
      const user = JSON.parse(userData)
      userId.value = user.id

      const response = await fetch(`${API_BASE_URL}/users/view/${user.id}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      if (response.ok) {
        const data = await response.json()
        if (data.success) {
          form.value.nome = data.user.nome || ''
          form.value.email = data.user.email || ''
          form.value.telefone = formatPhone(data.user.telefone || '')
          form.value.nivel_ingles = data.user.nivel_ingles || 'iniciante'
          form.value.idioma_preferido = data.user.idioma_preferido || 'pt-BR'
          form.value.status = data.user.status || 'ativo'
          form.value.objetivos_aprendizado = data.user.objetivos_aprendizado || ''
        }
      } else {
        form.value.nome = user.nome || ''
        form.value.email = user.email || ''
        form.value.telefone = formatPhone(user.telefone || '')
        form.value.nivel_ingles = user.nivel_ingles || 'iniciante'
        form.value.idioma_preferido = user.idioma_preferido || 'pt-BR'
        form.value.status = user.status || 'ativo'
        form.value.objetivos_aprendizado = user.objetivos_aprendizado || ''
      }
    } catch (e) {
      console.error('Erro ao carregar dados do usuário:', e)
      const user = JSON.parse(userData)
      userId.value = user.id
      form.value.nome = user.nome || ''
      form.value.email = user.email || ''
      form.value.telefone = formatPhone(user.telefone || '')
      form.value.nivel_ingles = user.nivel_ingles || 'iniciante'
      form.value.idioma_preferido = user.idioma_preferido || 'pt-BR'
      form.value.status = user.status || 'ativo'
      form.value.objetivos_aprendizado = user.objetivos_aprendizado || ''
    }
  }

  const handleSubmit = async () => {
    let currentUserId = userId.value

    if (!currentUserId) {
      const userData = localStorage.getItem('user')
      if (userData) {
        const user = JSON.parse(userData)
        currentUserId = user.id
        userId.value = currentUserId
      }
    }

    if (!currentUserId) {
      error('ID do usuário não encontrado. Faça login novamente.')
      router.push('/login')
      return
    }

    // Validar telefone
    if (form.value.telefone) {
      const digits = form.value.telefone.replace(/\D/g, '')
      if (digits.length > 0 && digits.length < 11) {
        error('Telefone deve ter 11 dígitos')
        return
      }
    }

    // Validar senha
    if (form.value.nova_senha) {
      if (form.value.nova_senha.length < 6) {
        error('A nova senha deve ter pelo menos 6 caracteres')
        return
      }
      if (form.value.nova_senha !== form.value.confirmar_senha) {
        error('As senhas não coincidem')
        return
      }
    }

    loading.value = true

    try {
      const submitData: any = {}

      if (form.value.nome) submitData.nome = form.value.nome
      if (form.value.email) submitData.email = form.value.email
      if (form.value.telefone) submitData.telefone = form.value.telefone
      if (form.value.nivel_ingles) submitData.nivel_ingles = form.value.nivel_ingles
      if (form.value.idioma_preferido) submitData.idioma_preferido = form.value.idioma_preferido
      if (form.value.status) submitData.status = form.value.status
      if (form.value.objetivos_aprendizado) submitData.objetivos_aprendizado = form.value.objetivos_aprendizado
      if (form.value.nova_senha) submitData.senha = form.value.nova_senha

      const response = await fetch(`${API_BASE_URL}/users/update/${currentUserId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(submitData),
      })

      const data = await response.json()

      if (response.ok && data.success) {
        const updatedUser = data.user || {
          id: currentUserId,
          nome: form.value.nome,
          email: form.value.email,
          telefone: form.value.telefone,
          nivel_ingles: form.value.nivel_ingles,
          idioma_preferido: form.value.idioma_preferido,
          status: form.value.status,
          objetivos_aprendizado: form.value.objetivos_aprendizado,
        }

        localStorage.setItem('user', JSON.stringify(updatedUser))
        window.dispatchEvent(new CustomEvent('user-updated', { detail: updatedUser }))
        success('Perfil atualizado com sucesso!')

        setTimeout(() => {
          router.push('/profile')
        }, 1500)
      } else {
        error(data.message || 'Erro ao atualizar perfil')
      }
    } catch (err) {
      console.error('Erro detalhado:', err)
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadUserData()
  })

  return {
    form,
    loading,
    showPassword,
    showConfirmPassword,
    handleSubmit,
  }
}

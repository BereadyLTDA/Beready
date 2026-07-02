import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { API_BASE_URL } from '@/shared/config/env'

// Composables internos para evitar erros de import
function useForm(initialData: any) {
  const form = ref(initialData)
  const errors = ref({})

  const validate = (rules: any) => {
    let isValid = true
    const newErrors: any = {}

    for (const field in rules) {
      const rule = rules[field]
      const error = rule(form.value[field])
      if (error) {
        newErrors[field] = error
        isValid = false
      }
    }

    errors.value = newErrors
    return isValid
  }

  const resetForm = () => {
    form.value = { ...initialData }
    errors.value = {}
  }

  return { form, errors, validate, resetForm }
}

function usePasswordStrength() {
  const strengthClass = ref('')
  const strengthText = ref('')
  const strengthWidth = ref('0%')

  const checkPasswordStrength = (password: string) => {
    let score = 0

    if (!password) {
      strengthClass.value = ''
      strengthText.value = ''
      strengthWidth.value = '0%'
      return
    }

    if (password.length >= 6) score += 1
    if (password.length >= 8) score += 1
    if (/[A-Z]/.test(password)) score += 1
    if (/[a-z]/.test(password)) score += 1
    if (/[0-9]/.test(password)) score += 1
    if (/[^A-Za-z0-9]/.test(password)) score += 1

    if (score <= 2) {
      strengthText.value = 'Fraca'
      strengthClass.value = 'weak'
      strengthWidth.value = '25%'
    } else if (score <= 4) {
      strengthText.value = 'Média'
      strengthClass.value = 'medium'
      strengthWidth.value = '50%'
    } else if (score <= 6) {
      strengthText.value = 'Forte'
      strengthClass.value = 'strong'
      strengthWidth.value = '75%'
    } else {
      strengthText.value = 'Muito Forte'
      strengthClass.value = 'very-strong'
      strengthWidth.value = '100%'
    }
  }

  return { strengthClass, strengthText, strengthWidth, checkPasswordStrength }
}

function usePhoneMask() {
  const phoneError = ref('')

  const handlePhoneInput = (event: Event) => {
    const input = event.target as HTMLInputElement
    let value = input.value.replace(/\D/g, '')

    if (value.length > 11) {
      value = value.slice(0, 11)
    }

    let formatted = value
    if (value.length === 11) {
      formatted = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1)$2-$3')
    } else if (value.length === 10) {
      formatted = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1)$2-$3')
    }

    input.value = formatted

    if (value.length > 0 && value.length < 11) {
      phoneError.value = 'Telefone deve ter 10 ou 11 dígitos'
    } else {
      phoneError.value = ''
    }

    return formatted
  }

  const handlePhoneKeydown = (event: KeyboardEvent) => {
    const allowedKeys = [
      'Backspace',
      'Delete',
      'ArrowLeft',
      'ArrowRight',
      'Tab',
      'Escape',
      'Enter',
      'Home',
      'End',
    ]
    if (allowedKeys.includes(event.key)) return
    if (!/^[0-9]$/.test(event.key)) {
      event.preventDefault()
    }
  }

  return { phoneError, handlePhoneInput, handlePhoneKeydown }
}

function useAlert() {
  const showAlert = ref(false)
  const alertMessage = ref('')
  const alertType = ref<'success' | 'error' | 'warning' | 'info'>('success')

  const success = (message: string) => {
    alertMessage.value = message
    alertType.value = 'success'
    showAlert.value = true
    setTimeout(() => {
      showAlert.value = false
    }, 3000)
  }

  const error = (message: string) => {
    alertMessage.value = message
    alertType.value = 'error'
    showAlert.value = true
    setTimeout(() => {
      showAlert.value = false
    }, 3000)
  }

  const warning = (message: string) => {
    alertMessage.value = message
    alertType.value = 'warning'
    showAlert.value = true
    setTimeout(() => {
      showAlert.value = false
    }, 3000)
  }

  const info = (message: string) => {
    alertMessage.value = message
    alertType.value = 'info'
    showAlert.value = true
    setTimeout(() => {
      showAlert.value = false
    }, 3000)
  }

  return { showAlert, alertMessage, alertType, success, error, warning, info }
}

// Exportação principal da função useRegister
export function useRegister() {
  const router = useRouter()
  const { success, error } = useAlert()
  const loading = ref(false)
  const showPassword = ref(false)
  const showConfirmPassword = ref(false)

  const { strengthClass, strengthText, strengthWidth, checkPasswordStrength } =
    usePasswordStrength()
  const { handlePhoneInput, handlePhoneKeydown, phoneError } = usePhoneMask()

  const { form, errors, validate } = useForm({
    nome: '',
    email: '',
    telefone: '',
    senha: '',
    confirmar_senha: '',
    nivel_ingles: '',
    idioma_preferido: '',
    objetivos_aprendizado: '',
  })

  const passwordsMatch = computed(() => form.value.senha === form.value.confirmar_senha)

  const rules = {
    nome: (value: string) => (!value ? 'Nome é obrigatório' : null),
    email: (value: string) => {
      if (!value) return 'E-mail é obrigatório'
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'E-mail inválido'
      return null
    },
    senha: (value: string) => {
      if (!value) return 'Senha é obrigatória'
      if (value.length < 6) return 'A senha deve ter pelo menos 6 caracteres'
      return null
    },
    confirmar_senha: (value: string) => {
      if (!value) return 'Confirmação de senha é obrigatória'
      if (form.value.senha !== value) return 'As senhas não coincidem'
      return null
    },
  }

  const checkPasswordMatch = () => {
    if (form.value.confirmar_senha) {
      validate(rules)
    }
  }

  const handleSubmit = async () => {
    if (form.value.telefone) {
      const digits = form.value.telefone.replace(/\D/g, '')
      if (digits.length > 0 && digits.length < 11) {
        error('Telefone deve ter 11 dígitos')
        return
      }
    }

    if (!validate(rules)) return

    loading.value = true

    try {
      const response = await fetch('${API_BASE_URL}/auth/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          nome: form.value.nome,
          email: form.value.email,
          senha: form.value.senha,
          telefone: form.value.telefone,
          nivel_ingles: form.value.nivel_ingles || 'iniciante',
          idioma_preferido: form.value.idioma_preferido || 'pt-BR',
          objetivos_aprendizado: form.value.objetivos_aprendizado,
        }),
      })

      const data = await response.json()

      if (response.ok && data.success) {
        success('Cadastro realizado com sucesso! Redirecionando...')
        setTimeout(() => router.push('/login'), 2000)
      } else {
        error(data.message || 'Erro ao cadastrar')
      }
    } catch (err) {
      console.error('Erro:', err)
      error('Erro de conexão com o servidor')
    } finally {
      loading.value = false
    }
  }

  return {
    form: form.value,
    errors: errors.value,
    loading,
    showPassword,
    showConfirmPassword,
    strengthClass,
    strengthText,
    strengthWidth,
    phoneError,
    passwordsMatch,
    handlePhoneInput,
    handlePhoneKeydown,
    checkPasswordStrength,
    checkPasswordMatch,
    handleSubmit,
  }
}

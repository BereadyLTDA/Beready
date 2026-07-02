import { ref, computed } from 'vue'

export function usePasswordValidation() {
  const password = ref('')
  const confirmPassword = ref('')
  const passwordError = ref('')
  const confirmPasswordError = ref('')

  const validatePassword = (value: string): string | null => {
    if (!value) return null
    if (value.length < 6) {
      return 'A senha deve ter pelo menos 6 caracteres'
    }
    return null
  }

  const validateConfirmPassword = (value: string): string | null => {
    if (!value) return null
    if (password.value !== value) {
      return 'As senhas não coincidem'
    }
    return null
  }

  const checkPasswordStrength = (value: string) => {
    let strength = 0
    if (value.length >= 8) strength++
    if (value.match(/[a-z]/) && value.match(/[A-Z]/)) strength++
    if (value.match(/\d/)) strength++
    if (value.match(/[^a-zA-Z\d]/)) strength++

    const strengthMap = {
      0: { text: 'Muito Fraca', class: '', width: '0%' },
      1: { text: 'Fraca', class: 'weak', width: '25%' },
      2: { text: 'Moderada', class: 'medium', width: '50%' },
      3: { text: 'Forte', class: 'strong', width: '75%' },
      4: { text: 'Muito Forte', class: 'very-strong', width: '100%' },
    }

    return strengthMap[strength as keyof typeof strengthMap]
  }

  const isPasswordValid = computed(() => {
    if (!password.value) return true
    return password.value.length >= 6
  })

  const isConfirmPasswordValid = computed(() => {
    if (!confirmPassword.value) return true
    return password.value === confirmPassword.value
  })

  const passwordsMatch = computed(() => {
    if (!password.value || !confirmPassword.value) return true
    return password.value === confirmPassword.value
  })

  const handlePasswordInput = (value: string) => {
    password.value = value
    passwordError.value = validatePassword(value) || ''
    if (confirmPassword.value) {
      confirmPasswordError.value = validateConfirmPassword(confirmPassword.value) || ''
    }
  }

  const handleConfirmPasswordInput = (value: string) => {
    confirmPassword.value = value
    confirmPasswordError.value = validateConfirmPassword(value) || ''
  }

  const resetPasswords = () => {
    password.value = ''
    confirmPassword.value = ''
    passwordError.value = ''
    confirmPasswordError.value = ''
  }

  return {
    password,
    confirmPassword,
    passwordError,
    confirmPasswordError,
    isPasswordValid,
    isConfirmPasswordValid,
    passwordsMatch,
    validatePassword,
    validateConfirmPassword,
    checkPasswordStrength,
    handlePasswordInput,
    handleConfirmPasswordInput,
    resetPasswords,
  }
}

import { ref, computed } from 'vue'

export function usePasswordStrength() {
  const strength = ref(0)
  const strengthText = ref('')
  const strengthClass = ref('')

  const checkPasswordStrength = (password: string) => {
    let score = 0
    
    if (!password) {
      strength.value = 0
      strengthText.value = ''
      strengthClass.value = ''
      return
    }
    
    if (password.length >= 6) score += 1
    if (password.length >= 8) score += 1
    if (/[A-Z]/.test(password)) score += 1
    if (/[a-z]/.test(password)) score += 1
    if (/[0-9]/.test(password)) score += 1
    if (/[^A-Za-z0-9]/.test(password)) score += 1
    
    strength.value = score
    
    if (score <= 2) {
      strengthText.value = 'Fraca'
      strengthClass.value = 'weak'
    } else if (score <= 4) {
      strengthText.value = 'Média'
      strengthClass.value = 'medium'
    } else if (score <= 6) {
      strengthText.value = 'Forte'
      strengthClass.value = 'strong'
    } else {
      strengthText.value = 'Muito Forte'
      strengthClass.value = 'very-strong'
    }
  }

  const strengthWidth = computed(() => {
    if (strength.value === 0) return '0%'
    return `${(strength.value / 7) * 100}%`
  })

  return {
    strength,
    strengthText,
    strengthClass,
    strengthWidth,
    checkPasswordStrength
  }
}

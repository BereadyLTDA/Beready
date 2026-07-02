import { ref } from 'vue'

export function usePhoneMask() {
  const phoneError = ref('')

  const handlePhoneInput = (event: Event) => {
    const input = event.target as HTMLInputElement
    let value = input.value.replace(/\D/g, '')
    
    if (value.length > 11) {
      value = value.slice(0, 11)
    }
    
    if (value.length === 11) {
      input.value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1)$2-$3')
    } else if (value.length === 10) {
      input.value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1)$2-$3')
    } else {
      input.value = value
    }
    
    if (value.length > 0 && value.length < 11) {
      phoneError.value = 'Telefone deve ter 10 ou 11 dígitos'
    } else {
      phoneError.value = ''
    }
    
    return input.value
  }

  const handlePhoneKeydown = (event: KeyboardEvent) => {
    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Escape', 'Enter']
    if (allowedKeys.includes(event.key)) return
    if (!/^[0-9]$/.test(event.key)) {
      event.preventDefault()
    }
  }

  const formatPhone = (phone: string) => {
    const digits = phone.replace(/\D/g, '')
    if (digits.length === 11) {
      return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1)$2-$3')
    }
    if (digits.length === 10) {
      return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1)$2-$3')
    }
    return phone
  }

  return {
    phoneError,
    handlePhoneInput,
    handlePhoneKeydown,
    formatPhone
  }
}

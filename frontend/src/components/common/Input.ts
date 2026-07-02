import { ref } from 'vue'

export function useInput() {
  const showPassword = ref(false)

  const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value
  }

  const getInputType = (type: string) => {
    if (type === 'password') {
      return showPassword.value ? 'text' : 'password'
    }
    return type
  }

  return {
    showPassword,
    togglePasswordVisibility,
    getInputType,
  }
}

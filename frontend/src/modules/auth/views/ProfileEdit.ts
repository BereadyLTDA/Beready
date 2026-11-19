import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useForm } from '@/shared/composables/useForm'
import { usePasswordStrength } from '@/shared/composables/usePasswordStrength'
import { usePhoneMask } from '@/shared/composables/usePhoneMask'
import { useAlert } from '@/shared/composables/useAlert'
import api, { getApiErrorMessage } from '@/core/services/api'
import { useAuthStore } from '@/stores/auth'
import type { ApiResponse, User } from '@/core/types'
import { useI18n } from 'vue-i18n'

export function useProfileEdit() {
  const router = useRouter()
  const authStore = useAuthStore()
  const { success, error } = useAlert()
  const { t } = useI18n()
  const loading = ref(false)
  const userId = ref<number | null>(null)
  const selectedImage = ref<File | undefined>(undefined)

  const { strengthClass, strengthText, strengthWidth, checkPasswordStrength } =
    usePasswordStrength()
  const { handlePhoneInput, handlePhoneKeydown, phoneError, formatPhone } = usePhoneMask()

  const { form, errors } = useForm({
    nome: '',
    email: '',
    telefone: '',
    foto_perfil: '',
    nivel_ingles: '',
    idioma_preferido: '',
    status: 'ativo',
    objetivos_aprendizado: '',
    nova_senha: '',
    confirmar_senha: '',
    senha_atual: '',
  })

  // O backend pede a senha atual para trocar a senha ou o e-mail (contas criadas com senha)
  const emailMudou = computed(
    () => form.email.trim().toLowerCase() !== (authStore.user?.email ?? '').toLowerCase(),
  )
  const precisaSenhaAtual = computed(() => !!form.nova_senha || emailMudou.value)

  const passwordsMatch = computed(() => {
    if (!form.nova_senha && !form.confirmar_senha) return true
    return form.nova_senha === form.confirmar_senha
  })

  const imagePreview = ref<string | null>(null)

  watch(selectedImage, (newFile) => {
    if (newFile) {
      const reader = new FileReader()
      reader.onload = (e) => {
        imagePreview.value = e.target?.result as string
      }
      reader.readAsDataURL(newFile)
    } else {
      imagePreview.value = null
    }
  })

  const handlePasswordInput = (event: Event) => {
    const input = event.target as HTMLInputElement
    checkPasswordStrength(input.value)
  }

  const handleImageChange = (event: Event) => {
    const input = event.target as HTMLInputElement

    if (!input.files || input.files.length === 0) {
      selectedImage.value = undefined
      return
    }

    const file = input.files[0]
    if (!file) {
      selectedImage.value = undefined
      return
    }

    selectedImage.value = file
  }

  const fillForm = (user: Partial<User>) => {
    form.nome = user.nome || ''
    form.email = user.email || ''
    form.telefone = formatPhone(user.telefone || '')
    form.foto_perfil = user.foto_perfil || ''
    form.nivel_ingles = user.nivel_ingles || 'iniciante'
    form.idioma_preferido = user.idioma_preferido || 'pt-BR'
    form.status = user.status || 'ativo'
    form.objetivos_aprendizado = user.objetivos_aprendizado || ''
  }

  const loadUserData = async () => {
    const currentUser = authStore.user
    if (!currentUser) {
      router.push('/login')
      return
    }

    userId.value = currentUser.id
    // Preenche imediatamente com o store e depois atualiza com o servidor
    fillForm(currentUser)

    try {
      const freshUser = await authStore.fetchMe()
      if (freshUser) {
        fillForm(freshUser)
      }
    } catch (e) {
      console.error('Erro ao carregar usuário:', e)
    }
  }

  const uploadImageToCloudinary = async (file: File): Promise<string | null> => {
    try {
      const formData = new FormData()
      formData.append('photo', file)

      const { data } = await api.post('/upload/profile-photo', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      if (data.success) {
        return data.url ?? null
      }
      console.error('Erro no upload:', data.message)
      return null
    } catch (error) {
      console.error('Erro no upload:', error)
      return null
    }
  }

  const handleSubmit = async () => {
    const currentUserId = userId.value ?? authStore.user?.id ?? null
    userId.value = currentUserId

    if (!currentUserId) {
      error(t('errors.unauthorized'))
      router.push('/login')
      return
    }

    if (form.telefone) {
      const digits = form.telefone.replace(/\D/g, '')
      if (digits.length > 0 && digits.length < 11) {
        error(t('profile.telefoneInvalido'))
        return
      }
    }

    if (form.nova_senha) {
      if (form.nova_senha.length < 6) {
        error(t('errors.minLength', { min: 6 }))
        return
      }
      if (form.nova_senha !== form.confirmar_senha) {
        error(t('errors.passwordMatch'))
        return
      }
    }

    loading.value = true

    try {
      let uploadedImageUrl = form.foto_perfil

      if (selectedImage.value) {
        const url = await uploadImageToCloudinary(selectedImage.value)
        if (url) {
          uploadedImageUrl = url
          selectedImage.value = undefined
          imagePreview.value = null
        } else {
          error(t('profile.erroUploadImagem'))
          loading.value = false
          return
        }
      }

      interface SubmitData {
        nome: string
        email: string
        telefone: string
        nivel_ingles: string
        idioma_preferido: string
        status: string
        objetivos_aprendizado: string
        foto_perfil: string
        senha?: string
        senha_atual?: string
      }

      const submitData: SubmitData = {
        nome: form.nome,
        email: form.email,
        telefone: form.telefone,
        nivel_ingles: form.nivel_ingles,
        idioma_preferido: form.idioma_preferido,
        status: form.status,
        objetivos_aprendizado: form.objetivos_aprendizado,
        foto_perfil: uploadedImageUrl,
      }

      if (form.nova_senha !== '') {
        submitData.senha = form.nova_senha
      }
      if (precisaSenhaAtual.value && form.senha_atual !== '') {
        submitData.senha_atual = form.senha_atual
      }

      const { data } = await api.put<ApiResponse<{ user: User }>>(`/users/update/${currentUserId}`, submitData)

      if (data.success) {
        const returnedUser = data.data?.user
        if (returnedUser?.id) {
          authStore.setUser(returnedUser)
        } else if (authStore.user) {
          authStore.setUser({
            ...authStore.user,
            nome: form.nome,
            email: form.email,
            telefone: form.telefone,
            foto_perfil: uploadedImageUrl,
            nivel_ingles: form.nivel_ingles,
            idioma_preferido: form.idioma_preferido,
            status: form.status,
            objetivos_aprendizado: form.objetivos_aprendizado,
          })
        }

        form.senha_atual = ''
        form.nova_senha = ''
        form.confirmar_senha = ''
        success(t('success.updated'))

        setTimeout(() => {
          router.push('/profile')
        }, 1500)
      } else {
        error(data.message || t('errors.serverError'))
      }
    } catch (err: unknown) {
      error(getApiErrorMessage(err) || t('errors.networkError'))
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadUserData()
  })

  return {
    form,
    errors,
    loading,
    strengthClass,
    strengthText,
    strengthWidth,
    phoneError,
    passwordsMatch,
    imagePreview,
    selectedImage,
    handlePhoneInput,
    handlePhoneKeydown,
    checkPasswordStrength,
    handlePasswordInput,
    handleImageChange,
    handleSubmit,
    precisaSenhaAtual,
  }
}

import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import api, { getApiErrorMessage } from '@/core/services/api'
import router from '@/router'
import i18n, { setLocale, toAppLocale, toUserLanguage } from '@/locales'
import { usePermissionStore } from '@/stores/permissionStore'
import type { ApiResponse, LoginResponseData, RefreshResponseData, User } from '@/core/types'

export interface AuthResult {
  success: boolean
  message?: string
  /** Código do backend para casos com tratamento próprio (ex.: 'email_nao_verificado') */
  codigo?: string
}

export interface LoginCredentials {
  email: string
  password: string
  recaptcha_token: string
}

export interface LogoutOptions {
  // Chama POST /auth/logout para o backend limpar o cookie de refresh (padrão: true)
  callServer?: boolean
  // Navega para esta rota após limpar a sessão; false para não navegar (padrão: login)
  redirectTo?: string | false
}

/**
 * Store ÚNICO de autenticação.
 *
 * O access token vive somente em memória. Após um reload, `init()` reidrata a
 * sessão via POST /auth/refresh (cookie httpOnly enviado pelo navegador).
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const accessToken = ref<string | null>(null)
  const initialized = ref(false)

  let initPromise: Promise<void> | null = null
  let refreshPromise: Promise<boolean> | null = null
  let userContextPromise: Promise<void> | null = null

  const isAuthenticated = computed(() => !!accessToken.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  const setUser = (newUser: User | null): void => {
    user.value = newUser
  }

  // Logado, o idioma do app é o salvo no usuário (users.idioma_preferido).
  // Cobre login, login social, refresh no boot, /users/me e edição do perfil.
  watch(
    () => user.value?.idioma_preferido,
    (idioma) => {
      if (idioma) setLocale(idioma)
    },
  )

  // Carrega dados dependentes do usuário (permissões e assinatura/limites do plano)
  const loadUserContext = async (): Promise<void> => {
    const permissionStore = usePermissionStore()
    await Promise.all([permissionStore.loadPermissions(), permissionStore.loadAssinatura()])
  }

  const clearSession = (): void => {
    user.value = null
    accessToken.value = null
    userContextPromise = null
    usePermissionStore().reset()
  }

  const startSession = async (data: LoginResponseData): Promise<void> => {
    accessToken.value = data.tokens.access_token
    user.value = data.user
    await loadUserContext()
  }

  // skipAuthRefresh: usado durante o próprio refresh para não aguardar a si mesmo
  const fetchMe = async (skipAuthRefresh = false): Promise<User | null> => {
    const response = await api.get<ApiResponse<User | { user: User }>>('/users/me', {
      skipAuthRefresh,
    })
    const payload = response.data.data
    const me = payload && 'user' in payload ? payload.user : payload
    if (me?.id) {
      user.value = me
    }
    return user.value
  }

  const handleLoginResponse = async (
    request: Promise<{ data: ApiResponse<LoginResponseData> }>,
    fallbackMessage: string,
  ): Promise<AuthResult> => {
    try {
      const response = await request
      if (response.data.success && response.data.data?.tokens?.access_token) {
        await startSession(response.data.data)
        return { success: true }
      }
      return { success: false, message: response.data.message ?? fallbackMessage }
    } catch (err: unknown) {
      const codigo = (err as { response?: { data?: { errors?: { codigo?: string } } } })?.response?.data?.errors
        ?.codigo
      return { success: false, message: getApiErrorMessage(err) ?? fallbackMessage, codigo }
    }
  }

  const login = (credentials: LoginCredentials): Promise<AuthResult> =>
    handleLoginResponse(
      api.post<ApiResponse<LoginResponseData>>('/auth/login', credentials),
      i18n.global.t('login.genericError'),
    )

  const loginWithSocialCode = (code: string): Promise<AuthResult> =>
    handleLoginResponse(
      api.post<ApiResponse<LoginResponseData>>('/auth/social/exchange', { code }),
      i18n.global.t('login.socialLoginFailed'),
    )

  /**
   * Renova o access token usando o cookie httpOnly. Chamadas concorrentes
   * compartilham a mesma promise (apenas um POST /auth/refresh por vez).
   */
  const refresh = (): Promise<boolean> => {
    if (!refreshPromise) {
      refreshPromise = (async () => {
        try {
          const response = await api.post<ApiResponse<RefreshResponseData>>('/auth/refresh')
          const data = response.data.data
          if (!response.data.success || !data?.access_token) {
            return false
          }
          accessToken.value = data.access_token
          if (data.user) {
            user.value = data.user
          } else {
            await fetchMe(true)
          }
          return !!user.value
        } catch {
          return false
        } finally {
          refreshPromise = null
        }
      })()
    }
    return refreshPromise
  }

  /**
   * Reidrata a sessão no boot do app (uma única vez).
   */
  const init = (): Promise<void> => {
    if (!initPromise) {
      initPromise = (async () => {
        try {
          const ok = await refresh()
          if (ok) {
            // Permissões e assinatura carregam em paralelo com a primeira tela (o menu e o
            // dashboard não dependem delas). Rotas com meta.permission aguardam waitUserContext().
            userContextPromise = loadUserContext().catch(() => undefined)
          } else {
            clearSession()
          }
        } finally {
          initialized.value = true
        }
      })()
    }
    return initPromise
  }

  /**
   * Logout centralizado: avisa o backend (limpa o cookie), limpa todos os
   * stores com dados do usuário e navega para o login.
   */
  const logout = async (options: LogoutOptions = {}): Promise<void> => {
    const { callServer = true, redirectTo = '/login' } = options

    if (callServer) {
      try {
        await api.post('/auth/logout')
      } catch {
        // Sessão já inválida no servidor: segue limpando o estado local
      }
    }

    clearSession()

    if (redirectTo !== false && router.currentRoute.value.path !== redirectTo) {
      await router.push(redirectTo)
    }
  }

  /**
   * Troca o idioma do app e, logado, grava em users.idioma_preferido para valer em
   * qualquer dispositivo. Se a gravação falhar, a troca continua valendo localmente.
   */
  const changeLanguage = async (value: string): Promise<boolean> => {
    const locale = toAppLocale(value)
    if (!locale) return false
    setLocale(locale)

    const current = user.value
    if (!current?.id) return true

    const idioma = toUserLanguage(locale)
    if (current.idioma_preferido === idioma) return true

    try {
      const { data } = await api.put<ApiResponse<{ user: User }>>(`/users/update/${current.id}`, {
        idioma_preferido: idioma,
      })
      user.value = data.data?.user?.id ? data.data.user : { ...current, idioma_preferido: idioma }
      return true
    } catch {
      return false
    }
  }

  return {
    user,
    accessToken,
    initialized,
    isAuthenticated,
    isAdmin,
    setUser,
    changeLanguage,
    login,
    loginWithSocialCode,
    refresh,
    init,
    waitUserContext: (): Promise<void> => userContextPromise ?? Promise.resolve(),
    logout,
    fetchMe,
  }
})

import { ref, reactive } from 'vue'
import { preferenciaService } from '../services/preferenciaService'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'
import { applyTheme, themeFromPreferences } from '@/shared/composables/useTheme'

// Tipo para erro da API
interface ApiError {
  response?: {
    status?: number
    data?: {
      message?: string
    }
  }
  message?: string
}

export function usePreferencias() {
  const { t } = useI18n()
  const loading = ref(false)
  const saving = ref(false)
  const { success, error } = useAlert()

  const form = reactive({
    tema: 'claro' as 'claro' | 'escuro',
    modo_daltonico: false,
    notificacoes_ativas: true,
    som_ativo: true,
    traducao_automatica: true,
    preferencia_dificuldade: 'intermediario' as
      | 'iniciante'
      | 'intermediario'
      | 'avancado'
      | 'adaptativo',
    meta_diaria_minutos: 45,
  })

  // Classes de tema são escritas só por useTheme.applyTheme (fonte única)
  const aplicarPreferenciasGlobais = () => {
    applyTheme(themeFromPreferences(form))
  }

  const fetchPreferencias = async (usuarioId: number) => {
    loading.value = true
    try {
      const response = await preferenciaService.getByUsuario(usuarioId)
      if (response.data.success && response.data.data) {
        const data = response.data.data
        form.tema = data.tema || 'claro'
        form.modo_daltonico = data.modo_daltonico || false
        form.notificacoes_ativas =
          data.notificacoes_ativas !== undefined ? data.notificacoes_ativas : true
        form.som_ativo = data.som_ativo !== undefined ? data.som_ativo : true
        form.traducao_automatica =
          data.traducao_automatica !== undefined ? data.traducao_automatica : true
        form.preferencia_dificuldade = data.preferencia_dificuldade || 'intermediario'
        form.meta_diaria_minutos = data.meta_diaria_minutos || 45
        aplicarPreferenciasGlobais()
      }
      return form
    } catch (err: unknown) {
      console.error('Erro ao carregar preferências:', err)
      const apiError = err as ApiError
      if (apiError.response?.status !== 404) {
        error(apiError.response?.data?.message || t('errors.serverError'))
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const savePreferencias = async (usuarioId: number) => {
    saving.value = true
    try {
      const data = {
        usuario_id: usuarioId,
        tema: form.tema,
        modo_daltonico: form.modo_daltonico,
        notificacoes_ativas: form.notificacoes_ativas,
        som_ativo: form.som_ativo,
        traducao_automatica: form.traducao_automatica,
        preferencia_dificuldade: form.preferencia_dificuldade,
        meta_diaria_minutos: form.meta_diaria_minutos,
      }

      const response = await preferenciaService.save(data)
      if (response.data.success) {
        aplicarPreferenciasGlobais()
        success(t('preferencias.salvarSucesso'))
      } else {
        error(response.data.message || t('preferencias.errorSave'))
      }
    } catch (err: unknown) {
      console.error('Erro ao salvar preferências:', err)
      const apiError = err as ApiError
      error(apiError.response?.data?.message || t('errors.networkError'))
    } finally {
      saving.value = false
    }
  }

  return {
    form,
    loading,
    saving,
    fetchPreferencias,
    savePreferencias,
    aplicarPreferenciasGlobais,
  }
}
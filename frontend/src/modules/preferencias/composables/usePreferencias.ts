import { ref, reactive } from 'vue'
import { preferenciaService } from '../services/preferenciaService'
import type { Preferencia } from '@/core/types'
import { useAlert } from '@/shared/composables/useAlert'

export function usePreferencias() {
  const preferencias = ref<Preferencia | null>(null)
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

  const aplicarTema = (tema: string) => {
    if (tema === 'escuro') {
      document.documentElement.classList.add('dark-mode')
      document.body.classList.add('dark-mode')
    } else {
      document.documentElement.classList.remove('dark-mode')
      document.body.classList.remove('dark-mode')
    }
  }

  const aplicarModoDaltonico = (ativo: boolean) => {
    if (ativo) {
      document.documentElement.classList.add('daltonico-mode')
      document.body.classList.add('daltonico-mode')
    } else {
      document.documentElement.classList.remove('daltonico-mode')
      document.body.classList.remove('daltonico-mode')
    }
  }

  const aplicarPreferenciasGlobais = () => {
    aplicarTema(form.tema)
    aplicarModoDaltonico(form.modo_daltonico)
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
    } catch (err: any) {
      console.error('Erro ao carregar preferências:', err)
      if (err.response?.status !== 404) {
        error(err.response?.data?.message || 'Erro ao carregar preferências')
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
        success('Preferências salvas com sucesso!')
      } else {
        error(response.data.message || 'Erro ao salvar preferências')
      }
    } catch (err: any) {
      console.error('Erro ao salvar preferências:', err)
      error(err.response?.data?.message || 'Erro de conexão com o servidor')
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

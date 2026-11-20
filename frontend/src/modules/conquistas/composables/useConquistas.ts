import { useI18n } from 'vue-i18n'
import { useAlert } from '@/shared/composables/useAlert'
import { conquistaService } from '../services/conquistaService'

/**
 * Avisa as conquistas novas (ganhas e ainda não vistas). Chamado depois de estudar: dashboard,
 * fim da revisão, fim do quiz e resultado da pronúncia. Falha em silêncio: é só um aviso.
 */
export function useConquistas() {
  const { t } = useI18n()
  const { success } = useAlert()

  const checarNovas = async (): Promise<void> => {
    try {
      const { data } = await conquistaService.listar()
      const novas = data.data.filter((c) => c.nova)
      if (!novas.length) return
      for (const c of novas) {
        success(t('conquistas.nova', { nome: t(`conquistas.itens.${c.codigo}.nome`) }), 6000)
      }
      await conquistaService.marcarVistas()
    } catch {
      // Sem aviso desta vez; a conquista continua registrada e aparece na página
    }
  }

  return { checarNovas }
}

import { beforeEach, describe, expect, it, vi } from 'vitest'

// t() devolve a própria chave (e os parâmetros), para o teste não depender das traduções
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>) =>
      params ? `${key} ${JSON.stringify(params)}` : key,
  }),
}))
vi.mock('@/shared/utils/intl', () => ({ formatDate: () => 'DATA-FORMATADA' }))

const getDevidos = vi.fn()
vi.mock('../services/flashcardService', () => ({
  flashcardService: { getDevidos: (...args: unknown[]) => getDevidos(...args) },
}))

import { useProximaRevisao, useRevisaoPendentes } from './useRevisao'

describe('useProximaRevisao', () => {
  // Meio-dia de 10/03/2026, no fuso da máquina que roda o teste
  const agora = new Date(2026, 2, 10, 12, 0, 0)
  const { proximaRevisao } = useProximaRevisao()

  it('card sem data, com data inválida ou já vencida está devido', () => {
    for (const valor of [
      null,
      undefined,
      '',
      'não é data',
      new Date(2026, 2, 10, 11, 59).toISOString(),
    ]) {
      expect(proximaRevisao(valor, agora)).toEqual({ devido: true, texto: 'revisao.devidoAgora' })
    }
  })

  it('revisão marcada para exatamente agora já conta como devida', () => {
    expect(proximaRevisao(agora.toISOString(), agora).devido).toBe(true)
  })

  it('mais tarde no mesmo dia: "hoje"', () => {
    const info = proximaRevisao(new Date(2026, 2, 10, 23, 0).toISOString(), agora)

    expect(info.devido).toBe(false)
    expect(info.texto).toContain('revisao.hoje')
  })

  it('no dia seguinte: "amanhã", mesmo virando o mês', () => {
    expect(proximaRevisao(new Date(2026, 2, 11, 0, 5).toISOString(), agora).texto).toContain(
      'revisao.amanha',
    )

    const fimDoMes = new Date(2026, 0, 31, 12, 0)
    const info = proximaRevisao(new Date(2026, 1, 1, 9, 0).toISOString(), fimDoMes)
    expect(info.texto).toContain('revisao.amanha')
  })

  it('daqui a mais dias: a data formatada', () => {
    const info = proximaRevisao(new Date(2026, 2, 15, 9, 0).toISOString(), agora)

    expect(info.devido).toBe(false)
    expect(info.texto).toContain('DATA-FORMATADA')
    expect(info.texto).not.toContain('revisao.amanha')
  })
})

describe('useRevisaoPendentes', () => {
  // Com chaves: o Vitest trata o que o beforeEach devolve como função de limpeza
  beforeEach(() => {
    getDevidos.mockReset()
  })

  it('carrega só a contagem (limite 1)', async () => {
    getDevidos.mockResolvedValue({ data: { data: { total: 7, flashcards: [] } } })
    const { total, loaded, carregar } = useRevisaoPendentes()

    expect(loaded.value).toBe(false)
    await carregar()

    expect(getDevidos).toHaveBeenCalledWith(1)
    expect(total.value).toBe(7)
    expect(loaded.value).toBe(true)
  })

  it('falha em silêncio: fica zero e marca como carregado', async () => {
    getDevidos.mockRejectedValue(new Error('rede'))
    const { total, loaded, carregar } = useRevisaoPendentes()

    await carregar()

    expect(total.value).toBe(0)
    expect(loaded.value).toBe(true)
  })
})

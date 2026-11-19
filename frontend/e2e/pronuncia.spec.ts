import type { Page } from '@playwright/test'
import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

/**
 * Simula a voz do navegador: speechSynthesis registra o que foi falado e o reconhecimento de voz
 * "entende" o texto guardado em window.__falaSimulada.
 */
const simularVoz = async (page: Page, entendido: string): Promise<void> => {
  await page.addInitScript((texto) => {
    const w = window as unknown as Record<string, unknown>
    w.__falado = []
    w.__falaSimulada = texto
    class Utterance {
      lang = ''
      rate = 1
      voice: unknown = null
      onend: (() => void) | null = null
      onerror: (() => void) | null = null
      constructor(public text: string) {}
    }
    w.SpeechSynthesisUtterance = Utterance
    Object.defineProperty(window, 'speechSynthesis', {
      value: {
        speak: (u: Utterance) => {
          ;(w.__falado as unknown[]).push({ text: u.text, lang: u.lang, rate: u.rate })
          setTimeout(() => u.onend?.(), 10)
        },
        cancel: () => {},
        getVoices: () => [],
      },
    })
    class Reconhecedor {
      lang = ''
      interimResults = false
      maxAlternatives = 1
      onresult: ((e: unknown) => void) | null = null
      onerror: ((e: unknown) => void) | null = null
      onend: (() => void) | null = null
      start() {
        setTimeout(() => {
          this.onresult?.({ results: [[{ transcript: String(w.__falaSimulada) }]] })
          this.onend?.()
        }, 50)
      }
      stop() {
        this.onend?.()
      }
      abort() {}
    }
    w.SpeechRecognition = Reconhecedor
    w.webkitSpeechRecognition = Reconhecedor
  }, entendido)
}

test.describe('Pronúncia', () => {
  test('repetir a frase mostra a nota do servidor e as palavras que faltaram', async ({ page, api }) => {
    api.asUser(makeUser())
    await simularVoz(page, 'where is the')
    await open(page, '/pronuncia')

    await expect(page.getByText('Where is the station?')).toBeVisible()

    // Ouvir usa a síntese de voz em inglês
    await page.getByRole('button', { name: t('pronuncia.ouvir'), exact: true }).first().click()
    await expect.poll(() => page.evaluate(() => (window as unknown as { __falado: unknown[] }).__falado)).toEqual([
      { text: 'Where is the station?', lang: 'en-US', rate: 1 },
    ])

    const tentativa = api.waitFor('POST', '/pronuncia/tentativas')
    await page.getByRole('button', { name: t('pronuncia.falar') }).click()
    expect((await tentativa).postDataJSON()).toEqual({ frase: 'Where is the station?', reconhecido: 'where is the' })

    await expect(page.getByText(t('pronuncia.notaTexto', { nota: 67 })).first()).toBeAttached()
    await expect(page.getByText(t('pronuncia.quase'))).toBeVisible()
    await expect(page.locator('mark', { hasText: 'station?' })).toBeVisible()
    // O resumo é atualizado depois da tentativa
    await expect(page.getByText('67%').first()).toBeVisible()

    await page.getByRole('button', { name: t('pronuncia.proxima') }).click()
    await expect(page.getByText('Nice to meet you.')).toBeVisible()
  })

  test('navegador sem reconhecimento de voz avisa e esconde o microfone', async ({ page, api }) => {
    api.asUser(makeUser())
    // Chrome e Edge têm a API nativa: remove para simular Firefox/Safari
    await page.addInitScript(() => {
      const w = window as unknown as Record<string, unknown>
      delete w.SpeechRecognition
      delete w.webkitSpeechRecognition
    })
    await open(page, '/pronuncia')

    await expect(page.getByText(t('pronuncia.naoSuportado'))).toBeVisible()
    await expect(page.getByRole('button', { name: t('pronuncia.falar') })).toHaveCount(0)
  })
})

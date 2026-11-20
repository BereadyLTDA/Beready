import type { Page } from '@playwright/test'
import { type QuizMock } from './support/api'
import { makeAssinaturaPremiumRecorrente, makeFlashcards, makeQuestoes, makeQuiz, makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

/** Projeto chromium-360 (viewport 360 x 740). */

const semRolagemHorizontal = async (page: Page, tela: string): Promise<void> => {
  // Dá tempo de fontes/imagens assentarem antes de medir
  await page.waitForLoadState('networkidle')
  const medidas = await page.evaluate(() => {
    const doc = document.documentElement
    // Elementos que passam da borda direita (para o relatório em caso de falha)
    const largos = Array.from(document.querySelectorAll<HTMLElement>('body *'))
      .filter((el) => {
        const r = el.getBoundingClientRect()
        return r.width > 0 && r.right > doc.clientWidth + 1
      })
      .slice(0, 5)
      .map((el) => `${el.tagName.toLowerCase()}.${Array.from(el.classList).slice(0, 3).join('.')}`)
    return { scrollWidth: doc.scrollWidth, clientWidth: doc.clientWidth, largos }
  })
  expect(medidas.scrollWidth, `${tela}: rolagem horizontal (${medidas.largos.join(', ')})`).toBeLessThanOrEqual(
    medidas.clientWidth,
  )
}

test.describe('Responsivo em 360px', () => {
  test('telas públicas sem rolagem horizontal', async ({ page }) => {
    for (const path of ['/', '/login', '/register', '/planos', '/forgot-password']) {
      await open(page, path)
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
      await semRolagemHorizontal(page, path)
    }
  })

  test('telas logadas sem rolagem horizontal', async ({ page, api }) => {
    const quiz: QuizMock = { ...makeQuiz(1, { titulo: 'Quiz com um título bem comprido para testar a quebra de linha' }), questoes: makeQuestoes(1) }
    api
      .asUser(makeUser(), { assinatura: makeAssinaturaPremiumRecorrente() })
      .withPreferencias()
      .withFlashcards(makeFlashcards(2))
      .withQuizes([quiz])

    const telas = [
      '/dashboard',
      '/flashcards',
      '/flashcards/1/study',
      '/flashcards/revisao',
      '/quizes',
      '/quizes/edit/1',
      '/quizes/1/play',
      '/planos',
      '/preferencias',
      '/pronuncia',
      '/conquistas',
      '/profile',
    ]
    for (const path of telas) {
      await open(page, path)
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
      await semRolagemHorizontal(page, path)
    }
  })

  test('menu vira gaveta: abre pelo botão, fecha com Esc e devolve o foco', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/dashboard')

    // Menu inline escondido; só o botão de abrir
    const abrir = page.getByRole('button', { name: t('ui.openMenu') })
    await expect(abrir).toBeVisible()
    await expect(abrir).toHaveAttribute('aria-expanded', 'false')
    await expect(page.getByRole('navigation', { name: t('ui.mainNavigation') })).toBeHidden()

    await abrir.click()
    const gaveta = page.getByRole('dialog', { name: t('ui.mainNavigation') })
    await expect(gaveta).toBeVisible()
    await expect(page.getByRole('button', { name: t('ui.closeMenu') }).first()).toHaveAttribute('aria-expanded', 'true')
    await expect(gaveta.getByRole('link', { name: t('common.flashcards') })).toBeVisible()
    // Foco vai para dentro da gaveta
    await expect.poll(() => gaveta.evaluate((el) => el.contains(document.activeElement))).toBe(true)
    // Rolagem do body bloqueada
    await expect(page.locator('body')).toHaveCSS('overflow', 'hidden')

    await page.keyboard.press('Escape')
    await expect(gaveta).toBeHidden()
    await expect(abrir).toBeFocused()
    await expect(abrir).toHaveAttribute('aria-expanded', 'false')
  })

  test('gaveta fecha pelo X, pelo fundo e ao navegar', async ({ page, api }) => {
    api.asUser(makeUser()).withFlashcards(makeFlashcards(0))
    await open(page, '/dashboard')

    const abrir = page.getByRole('button', { name: t('ui.openMenu') })
    const gaveta = page.getByRole('dialog', { name: t('ui.mainNavigation') })

    await abrir.click()
    await gaveta.getByRole('button', { name: t('ui.closeMenu') }).click()
    await expect(gaveta).toBeHidden()

    await abrir.click()
    await expect(gaveta).toBeVisible()
    // Clique no fundo escurecido (fora da gaveta, à esquerda em LTR)
    await page.mouse.click(10, 400)
    await expect(gaveta).toBeHidden()

    await abrir.click()
    await gaveta.getByRole('link', { name: t('common.flashcards') }).click()
    await expect(page).toHaveURL(/\/flashcards$/)
    await expect(gaveta).toBeHidden()
  })

  test('logout pela gaveta', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/dashboard')

    await page.getByRole('button', { name: t('ui.openMenu') }).click()
    const logout = api.waitFor('POST', '/auth/logout')
    await page.getByRole('dialog', { name: t('ui.mainNavigation') }).getByRole('button', { name: t('common.sair') }).click()
    await logout
    await expect(page).toHaveURL(/\/login$/)
  })
})

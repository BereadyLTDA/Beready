import AxeBuilder from '@axe-core/playwright'
import type { Page } from '@playwright/test'
import { type QuizMock } from './support/api'
import { makeAdmin, makeAssinaturaPremiumRecorrente, makeFlashcards, makeQuestoes, makeQuiz, makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'

/**
 * Checagem básica de acessibilidade (axe-core, WCAG 2.x A/AA) nas telas principais.
 * Falha só com violações de impacto "critical" ou "serious".
 */

interface Achado {
  regra: string
  impacto: string
  alvos: string[]
}

const violacoesGraves = async (page: Page): Promise<Achado[]> => {
  // Espera animações de entrada (modais, toasts) para não medir contraste no meio da transição
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(400)
  const { violations } = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    // Toasts somem sozinhos e não fazem parte da tela
    .exclude('.ui-toasts')
    .analyze()
  // Violações leves/moderadas não reprovam, mas ficam registradas no relatório do teste
  for (const v of violations.filter((v) => v.impact !== 'critical' && v.impact !== 'serious')) {
    test.info().annotations.push({
      type: `axe:${v.impact ?? 'n/a'}`,
      description: `${v.id} -> ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`,
    })
  }
  return violations
    .filter((v) => v.impact === 'critical' || v.impact === 'serious')
    .map((v) => ({ regra: v.id, impacto: v.impact ?? '', alvos: v.nodes.slice(0, 5).map((n) => n.target.join(' ')) }))
}

const verificar = async (page: Page, tela: string): Promise<void> => {
  const achados = await violacoesGraves(page)
  expect(achados, `${tela}: violações graves de acessibilidade\n${JSON.stringify(achados, null, 2)}`).toEqual([])
}

test.describe('Acessibilidade (axe)', () => {
  for (const path of ['/', '/login', '/register', '/planos', '/termos', '/privacidade']) {
    test(`pública ${path}`, async ({ page }) => {
      await open(page, path)
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
      await verificar(page, path)
    })
  }

  const telasLogadas = [
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
    '/nivelamento',
    '/progresso',
  ]

  for (const path of telasLogadas) {
    test(`logada ${path}`, async ({ page, api }) => {
      const quiz: QuizMock = { ...makeQuiz(1), questoes: makeQuestoes(1) }
      api
        .asUser(makeUser(), { assinatura: makeAssinaturaPremiumRecorrente() })
        .withPreferencias()
        .withFlashcards(makeFlashcards(2))
        .withQuizes([quiz])

      await open(page, path)
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
      await verificar(page, path)
    })
  }

  // Admin vê telas e itens a mais (item do menu, painel com abas)
  for (const [path, tema] of [
    ['/dashboard', 'claro'],
    ['/dashboard', 'escuro'],
    ['/admin', 'claro'],
    ['/admin?tab=stats', 'escuro'],
  ] as const) {
    test(`admin ${path} (${tema})`, async ({ page, api }) => {
      api.asUser(makeAdmin()).withPreferencias({ tema }).withFlashcards(makeFlashcards(1))
      await open(page, path)
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
      await verificar(page, `${path} admin (${tema})`)
    })
  }

  test('tema escuro no dashboard', async ({ page, api }) => {
    api.asUser(makeUser()).withPreferencias({ tema: 'escuro' }).withFlashcards(makeFlashcards(1))
    await open(page, '/dashboard')
    await expect(page.locator('html')).toHaveClass(/dark-mode/)
    await verificar(page, '/dashboard (escuro)')
  })
})

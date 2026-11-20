import { makeConquistas } from './support/api'
import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('Gamificação', () => {
  test('dashboard mostra a meta do dia e leva à revisão', async ({ page, api }) => {
    api.asUser(makeUser()).withMetaDiaria({ meta: 10, feitos_hoje: 3 })
    await open(page, '/dashboard')

    await expect(page.getByText(t('meta.titulo'), { exact: true }).first()).toBeVisible()
    await expect(page.getByText(t('meta.andamento', { feitos: 3, meta: 10 }))).toBeAttached()
    await page.getByRole('link', { name: t('meta.revisar') }).click()
    await expect(page).toHaveURL(/\/flashcards\/revisao$/)
  })

  test('meta batida troca o texto e oferece ver as conquistas', async ({ page, api }) => {
    api.asUser(makeUser()).withMetaDiaria({ meta: 5, feitos_hoje: 7 })
    await open(page, '/dashboard')

    await expect(page.getByText(t('meta.batida'))).toBeVisible()
    await expect(page.getByRole('link', { name: t('conquistas.ver') }).first()).toBeVisible()
  })

  test('conquista nova aparece como aviso uma vez e é marcada como vista', async ({ page, api }) => {
    const conquistas = makeConquistas().map((c) =>
      c.codigo === 'primeiro_card'
        ? { ...c, atual: 1, conquistada: true, conquistada_em: '2026-11-20T10:00:00-03:00', nova: true }
        : c,
    )
    api.asUser(makeUser()).withConquistas(conquistas)

    const vistas = api.waitFor('POST', '/conquistas/vistas')
    await open(page, '/dashboard')

    const nome = t('conquistas.itens.primeiro_card.nome')
    await expect(page.getByText(t('conquistas.nova', { nome }))).toBeVisible()
    await vistas
  })

  test('página de conquistas mostra as obtidas e o andamento das outras', async ({ page, api }) => {
    const conquistas = makeConquistas().map((c) => {
      if (c.codigo === 'primeiro_card') return { ...c, atual: 1, conquistada: true, conquistada_em: '2026-11-20T10:00:00-03:00' }
      if (c.codigo === 'cards_50') return { ...c, atual: 12 }
      return c
    })
    api.asUser(makeUser()).withConquistas(conquistas)
    await open(page, '/conquistas')

    await expect(page.getByRole('heading', { level: 1, name: t('conquistas.titulo') })).toBeVisible()
    await expect(page.getByText(t('conquistas.total', { feitas: 1, total: 8 }))).toBeVisible()
    await expect(page.getByRole('heading', { name: t('conquistas.itens.cards_50.nome') })).toBeVisible()
    await expect(page.getByText(t('conquistas.andamento', { atual: 12, alvo: 50 })).first()).toBeAttached()
  })

  test('preferências gravam a meta de cards por dia', async ({ page, api }) => {
    api.asUser(makeUser()).withPreferencias()
    await open(page, '/preferencias')

    await page.getByLabel(t('preferencias.metaCards')).selectOption('20')
    const salvar = api.waitFor('POST', '/preferencias')
    await page.getByRole('button', { name: t('preferencias.salvar') }).click()
    expect((await salvar).postDataJSON()).toMatchObject({ meta_diaria_cards: 20 })
  })
})

import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('Nivelamento', () => {
  test('dashboard convida quem ainda não fez o teste', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/dashboard')

    await expect(page.getByRole('heading', { name: t('nivelamento.convite') })).toBeVisible()
    await page.getByRole('link', { name: t('nivelamento.comecar') }).click()
    await expect(page).toHaveURL(/\/nivelamento$/)
  })

  test('quem já fez não vê o convite', async ({ page, api }) => {
    api.asUser(makeUser()).withNivelamentoFeito()
    await open(page, '/dashboard')

    await expect(page.getByText(t('dashboard.flashcardsCount'))).toBeVisible()
    await expect(page.getByRole('heading', { name: t('nivelamento.convite') })).toHaveCount(0)
  })

  test('responde uma questão por vez e mostra o nível no fim', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/nivelamento')

    await page.getByRole('button', { name: t('nivelamento.comecar') }).click()
    await expect(page.getByText('Question 1: She ___ a teacher.')).toBeVisible()

    // A primeira alternativa é a certa no mock
    for (const n of [1, 2, 3]) {
      await expect(page.getByText(`Question ${n}: She ___ a teacher.`)).toBeVisible()
      const resposta = api.waitFor('POST', '/nivelamento/1/responder')
      await page.getByRole('button', { name: 'is', exact: true }).click()
      expect((await resposta).postDataJSON()).toEqual({ alternativa: 0 })
    }

    await expect(page.getByText(t('nivelamento.seuNivel'), { exact: true })).toBeVisible()
    await expect(page.getByText('B2', { exact: true })).toBeVisible()
    await expect(page.getByText(t('nivelamento.niveis.B2.nome'))).toBeVisible()
  })

  test('resultado anterior aparece direto, com a data para refazer', async ({ page, api }) => {
    api.asUser(makeUser()).withNivelamentoFeito()
    await open(page, '/nivelamento')

    await expect(page.getByText(t('nivelamento.seuNivel'), { exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: t('nivelamento.comecar') })).toHaveCount(0)
  })
})

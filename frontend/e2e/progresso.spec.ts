import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('Progresso: gráficos', () => {
  test('mostra os quatro gráficos com resumo acessível e tabela de dados', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/progresso')

    // Revisões: 3 + 4 + 5 nos três últimos dias do mock
    await expect(page.getByRole('img', { name: t('progresso.graficos.resumoRevisoes', { total: 12 }) })).toBeVisible()
    // Acerto mais recente do mock: 70%
    await expect(page.getByRole('img', { name: t('progresso.graficos.resumoAcertos', { taxa: 70 }) })).toBeVisible()
    await expect(page.getByRole('img', { name: t('progresso.graficos.resumoPronuncia') })).toBeVisible()

    // Distribuição dos cards: 2 novos de 4 = 50%
    await expect(page.getByText('2 (50%)')).toBeVisible()
    await expect(page.getByText(t('progresso.graficos.dominados'))).toBeVisible()

    // A tabela equivalente abre pelo "ver dados em tabela"
    const primeiro = page.locator('figure').first()
    await primeiro.getByText(t('progresso.graficos.verTabela')).click()
    await expect(primeiro.getByRole('columnheader', { name: t('progresso.graficos.revisoes') })).toBeVisible()
    await expect(primeiro.getByRole('row')).toHaveCount(31)
  })
})

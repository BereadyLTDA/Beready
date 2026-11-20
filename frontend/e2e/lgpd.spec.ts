import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('LGPD', () => {
  test('termos e privacidade abrem sem login e linkam um ao outro', async ({ page }) => {
    await open(page, '/termos')
    await expect(page.getByRole('heading', { level: 1, name: t('legal.termos') })).toBeVisible()
    await expect(page.getByRole('heading', { name: '1. Sobre o BeReady' })).toBeVisible()

    await page.getByRole('main').getByRole('link', { name: t('legal.privacidade') }).last().click()
    await expect(page).toHaveURL(/\/privacidade$/)
    await expect(page.getByRole('heading', { name: '4. Com quem compartilhamos' })).toBeVisible()
    // Sem VITE_CONTATO_EMAIL, o contato aponta para o repositório
    await expect(page.getByRole('link', { name: 'github.com/BereadyLTDA/Beready' }).first()).toBeVisible()
  })

  test('cadastro sem o aceite não chama a API', async ({ page, api }) => {
    await open(page, '/register')
    await page.getByLabel(t('register.nome')).fill('Sem Aceite')
    await page.getByLabel(t('login.email')).fill('sem@beready.test')
    await page.getByRole('textbox', { name: t('register.senha'), exact: true }).fill('Senha@123')
    await page.getByRole('textbox', { name: t('register.confirmarSenha'), exact: true }).fill('Senha@123')
    await page.getByRole('button', { name: t('register.createAccount') }).click()

    await expect(page.getByText(t('legal.aceiteObrigatorio'))).toBeVisible()
    expect(api.calls('POST', '/auth/register')).toHaveLength(0)
  })

  test('login avisa que entrar com rede social é aceitar os termos', async ({ page }) => {
    await open(page, '/login')
    await expect(page.locator('.user-login__aviso-social')).toContainText(t('legal.termos'))
  })

  test('perfil exporta os dados num arquivo JSON', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/profile')

    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: t('profile.exportar') }).click()
    expect((await download).suggestedFilename()).toBe('beready-meus-dados.json')
    await expect(page.getByText(t('profile.exportado'))).toBeVisible()
  })

  test('excluir a conta manda a senha para confirmar', async ({ page, api }) => {
    const user = makeUser()
    api.asUser(user)
    await open(page, '/profile')

    await page.getByRole('button', { name: t('profile.deleteAccount') }).click()
    const dialogo = page.getByRole('dialog')
    await dialogo.getByRole('textbox', { name: new RegExp(t('profile.deleteConfirmLabel')) }).fill(user.email)
    await dialogo.getByLabel(t('profile.deleteSenha')).fill('senhaCerta1')

    const exclusao = api.waitFor('DELETE', `/users/delete/${user.id}`)
    await dialogo.getByRole('button', { name: t('profile.confirmDelete') }).click()
    expect((await exclusao).postDataJSON()).toEqual({ senha: 'senhaCerta1' })
    await expect(page.getByText(t('profile.deleteSuccess'))).toBeVisible()
  })
})

import { makeAdmin, makeFlashcards, makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('Sessão', () => {
  test('boot com refresh válido reidrata a sessão e abre o dashboard', async ({ page, api }) => {
    api.asUser(makeUser())

    const refresh = api.waitFor('POST', '/auth/refresh')
    await open(page, '/')

    await refresh
    // Logado, "/" vai para o dashboard
    await expect(page).toHaveURL(/\/dashboard$/)
    await expect(page.getByRole('heading', { level: 1, name: t('dashboard.welcome', { name: 'Maria' }) })).toBeVisible()
    // Contexto do usuário carregado no boot, com o access token em memória
    await expect.poll(() => api.calls('GET', '/user/permissions').length).toBeGreaterThan(0)
    await expect.poll(() => api.calls('GET', '/user/assinatura').length).toBeGreaterThan(0)
    expect(api.calls('GET', '/user/permissions')[0]?.headers.authorization).toBe('Bearer e2e-access-token')
    // Navbar com o usuário
    await expect(page.getByRole('navigation', { name: t('ui.mainNavigation') })).toBeVisible()
    await expect(page.getByRole('link', { name: t('common.dashboard') })).toHaveAttribute('aria-current', 'page')
    // Nome/e-mail só aparecem a partir de 1536px; em 1280px ficam o avatar e o "Sair"
    await expect(page.getByRole('button', { name: t('common.sair') })).toBeVisible()
  })

  test('dashboard mostra os cards para revisar hoje e leva à fila', async ({ page, api }) => {
    api.asUser(makeUser()).withFlashcards(makeFlashcards(2))

    await open(page, '/dashboard')

    const card = page.getByRole('link', { name: new RegExp(t('revisao.paraRevisarHojeLabel')) })
    await expect(card).toContainText('2')
    await expect(card).toContainText(t('revisao.revisarAgora'))
    // A contagem usa limite=1 (só o total interessa)
    await expect
      .poll(() => api.requests.some((r) => r.method === 'GET' && r.path === '/flashcards/revisao'))
      .toBe(true)

    await card.click()
    await expect(page).toHaveURL(/\/flashcards\/revisao$/)
    await expect(page.getByRole('heading', { level: 1, name: t('revisao.titulo') })).toBeVisible()
  })

  test('dashboard sem cards vencidos mostra "em dia"', async ({ page, api }) => {
    api.asUser(makeUser()).withFlashcards(makeFlashcards(0))
    await open(page, '/dashboard')

    const card = page.getByRole('link', { name: new RegExp(t('revisao.paraRevisarHojeLabel')) })
    await expect(card).toContainText('0')
    await expect(card).toContainText(t('revisao.emDia'))
    await expect(card).toHaveAttribute('href', '/flashcards')
  })

  test('logout chama /auth/logout e volta para o login', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/dashboard')

    const logout = api.waitFor('POST', '/auth/logout')
    await page.getByRole('button', { name: t('common.sair') }).click()
    await logout

    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByRole('status').filter({ hasText: t('success.logout') })).toBeVisible()

    // Sessão limpa: voltar ao dashboard pede login de novo
    await page.goto('/dashboard')
    await expect(page).toHaveURL(/\/login$/)
  })

  test('usuário comum em /admin volta para o dashboard', async ({ page, api }) => {
    api.asUser(makeUser())
    await open(page, '/admin')

    await expect(page).toHaveURL(/\/dashboard$/)
    expect(api.requests.some((r) => r.path.startsWith('/admin/'))).toBe(false)
  })

  test('admin abre o painel pelo menu Administrador do navbar', async ({ page, api }) => {
    api.asUser(makeAdmin())
    await open(page, '/dashboard')

    // Os atalhos ficam no menu "Administrador" do navbar
    const menuAdmin = page.getByRole('button', { name: t('admin.badge') })
    await menuAdmin.click()
    await page.getByRole('link', { name: t('admin.users') }).click()
    await expect(page).toHaveURL(/\/admin\?tab=users$/)
  })
})

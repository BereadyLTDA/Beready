import { API_URL, fail, ok } from './support/api'
import { makeUser } from './support/data'
import { expect, open, test } from './support/fixtures'
import { t } from './support/i18n'

test.describe('Páginas públicas', () => {
  test('home mostra o hero e leva ao cadastro e ao login', async ({ page }) => {
    await open(page, '/')

    await expect(page.getByRole('heading', { level: 1, name: 'Beready' })).toBeVisible()
    await expect(page.getByText(t('home.subtitle'))).toBeVisible()
    await expect(page.getByRole('heading', { name: t('home.comoFunciona') })).toBeVisible()

    await page.getByRole('link', { name: t('home.comecar') }).click()
    await expect(page).toHaveURL(/\/register$/)

    await page.goBack()
    await page.getByRole('link', { name: t('common.entrar') }).click()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('rota protegida sem sessão redireciona para o login', async ({ page, api }) => {
    const refresh = api.waitFor('POST', '/auth/refresh')
    await open(page, '/flashcards')

    expect((await refresh).method()).toBe('POST')
    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByRole('heading', { name: t('common.entrar') })).toBeVisible()
    // Sem sessão nenhuma rota de dados é chamada
    expect(api.calls('GET', '/flashcards')).toHaveLength(0)
  })
})

test.describe('Login', () => {
  test('valida os campos antes de enviar', async ({ page, api }) => {
    await open(page, '/login')

    const email = page.getByLabel(t('login.email'))
    const senha = page.getByRole('textbox', { name: t('login.password'), exact: true })
    const entrar = page.getByRole('button', { name: t('common.entrar'), exact: true })

    // Campos obrigatórios: o navegador bloqueia o envio vazio
    await entrar.click()
    await expect(email).toHaveJSProperty('validity.valueMissing', true)

    // Senha curta: mensagem do app no campo
    await email.fill('maria@beready.test')
    await senha.fill('123')
    await entrar.click()
    await expect(senha).toHaveAccessibleDescription(t('passwordValidation.minLength'))
    await expect(senha).toHaveAttribute('aria-invalid', 'true')
    expect(api.calls('POST', '/auth/login')).toHaveLength(0)

    // Mostrar/ocultar senha
    await page.getByRole('button', { name: t('ui.showPassword') }).click()
    await expect(senha).toHaveAttribute('type', 'text')
    await page.getByRole('button', { name: t('ui.hidePassword') }).click()
    await expect(senha).toHaveAttribute('type', 'password')
  })

  test('mostra o erro devolvido pela API', async ({ page, api }) => {
    await open(page, '/login')
    await page.getByLabel(t('login.email')).fill('maria@beready.test')
    await page.getByRole('textbox', { name: t('login.password'), exact: true }).fill('senhaerrada')

    const login = api.waitFor('POST', '/auth/login')
    await page.getByRole('button', { name: t('common.entrar'), exact: true }).click()

    const body = (await login).postDataJSON() as Record<string, unknown>
    expect(body).toMatchObject({ email: 'maria@beready.test', password: 'senhaerrada' })
    // Token vem do stub do reCAPTCHA (o real não pode ser resolvido no teste)
    expect(body.recaptcha_token).toBe('e2e-recaptcha-token')
    await expect(page.getByRole('alert').filter({ hasText: 'E-mail ou senha inválidos' })).toBeVisible()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('login com sucesso abre o dashboard', async ({ page, api }) => {
    const user = makeUser()
    api.on('POST', '/auth/login', () => {
      api.asUser(user)
      return ok({ user, tokens: { access_token: 'e2e-access-token', expires_in: 900, token_type: 'Bearer' } }, 'Login realizado')
    })

    await open(page, '/login')
    await page.getByLabel(t('login.email')).fill(user.email)
    await page.getByRole('textbox', { name: t('login.password'), exact: true }).fill('senha-correta')
    await page.getByRole('button', { name: t('common.entrar'), exact: true }).click()

    await expect(page).toHaveURL(/\/dashboard$/)
    await expect(page.getByRole('heading', { name: t('dashboard.welcome', { name: 'Maria' }) })).toBeVisible()
  })

  test('botões sociais redirecionam para o backend', async ({ page }) => {
    await open(page, '/login')

    await expect(page.getByRole('button', { name: t('login.loginWithFacebook') })).toBeVisible()
    await expect(page.getByRole('button', { name: t('login.loginWithLinkedIn') })).toBeVisible()

    // Navegação de página inteira para o backend (que redireciona ao provedor)
    const navegacao = page.waitForRequest((r) => r.isNavigationRequest() && r.url() === `${API_URL}/auth/login/google`)
    await page.getByRole('button', { name: t('login.loginWithGoogle') }).click()
    expect((await navegacao).method()).toBe('GET')
  })

  test('erro do login social volta com mensagem', async ({ page }) => {
    await open(page, '/login?error=social_auth_failed')
    await expect(page.getByRole('alert').filter({ hasText: t('login.socialAuthFailed') })).toBeVisible()
    await expect(page).toHaveURL(/\/login$/)
  })
})

test.describe('Cadastro', () => {
  test('máscara de telefone, força da senha e confirmação', async ({ page }) => {
    await open(page, '/register')

    const telefone = page.getByLabel(t('profile.telefone'))
    await telefone.pressSequentially('11a98')
    await expect(telefone).toHaveValue('(11) 98')
    await expect(telefone).toHaveAccessibleDescription(t('register.phoneInvalid'))
    await telefone.pressSequentially('7654321')
    await expect(telefone).toHaveValue('(11) 98765-4321')
    await expect(telefone).not.toHaveAttribute('aria-invalid', 'true')

    const senha = page.getByRole('textbox', { name: t('register.senha'), exact: true })
    await senha.fill('abc')
    await expect(page.getByText(t('passwordStrength.weak'), { exact: true })).toBeVisible()
    await senha.fill('abcdef12')
    await expect(page.getByText(t('passwordStrength.medium'), { exact: true })).toBeVisible()
    await senha.fill('Abcdef1!')
    await expect(page.getByText(t('passwordStrength.strong'), { exact: true })).toBeVisible()

    const confirmar = page.getByRole('textbox', { name: t('register.confirmarSenha'), exact: true })
    await confirmar.fill('Abcdef1')
    await expect(page.getByText(t('register.passwordsDoNotMatch'))).toBeVisible()
    await confirmar.fill('Abcdef1!')
    await expect(page.getByText(t('register.passwordsMatch'))).toBeVisible()
  })

  test('envio com sucesso chama a API e pede para confirmar o e-mail', async ({ page, api }) => {
    await open(page, '/register')

    await page.getByLabel(t('register.nome')).fill('João da Silva')
    await page.getByLabel(t('login.email')).fill('joao@beready.test')
    await page.getByLabel(t('profile.telefone')).pressSequentially('21912345678')
    await page.getByRole('textbox', { name: t('register.senha'), exact: true }).fill('Senha@123')
    await page.getByRole('textbox', { name: t('register.confirmarSenha'), exact: true }).fill('Senha@123')
    await page.getByLabel(t('profile.idiomaPreferido')).selectOption('en')
    // LGPD: o aceite é obrigatório
    await page.locator('.user-register__aceite .ui-check__box').click()
    await expect(page.getByRole('checkbox')).toBeChecked()

    const register = api.waitFor('POST', '/auth/register')
    await page.getByRole('button', { name: t('register.createAccount') }).click()

    expect((await register).postDataJSON()).toMatchObject({
      nome: 'João da Silva',
      email: 'joao@beready.test',
      senha: 'Senha@123',
      telefone: '(21) 91234-5678',
      nivel_ingles: 'iniciante',
      idioma_preferido: 'en',
      recaptcha_token: 'e2e-recaptcha-token',
      aceite_termos: true,
    })
    // A conta só entra depois do link: a tela mostra o aviso e o reenvio
    await expect(page.getByRole('heading', { name: t('verificarEmail.confiraTitulo') })).toBeVisible()
    await expect(page.getByText('joao@beready.test')).toBeVisible()

    const reenvio = api.waitFor('POST', '/auth/resend-verification')
    await page.getByRole('button', { name: t('verificarEmail.reenviar') }).click()
    expect((await reenvio).postDataJSON()).toEqual({ email: 'joao@beready.test' })
  })

  test('senhas diferentes bloqueiam o envio', async ({ page, api }) => {
    await open(page, '/register')
    await page.getByLabel(t('register.nome')).fill('João')
    await page.getByLabel(t('login.email')).fill('joao@beready.test')
    await page.getByRole('textbox', { name: t('register.senha'), exact: true }).fill('Senha@123')
    await page.getByRole('textbox', { name: t('register.confirmarSenha'), exact: true }).fill('Senha@124')
    await page.getByRole('button', { name: t('register.createAccount') }).click()

    await expect(page.getByRole('textbox', { name: t('register.confirmarSenha'), exact: true })).toHaveAccessibleDescription(
      t('passwordValidation.doNotMatch'),
    )
    expect(api.calls('POST', '/auth/register')).toHaveLength(0)
  })
})

test.describe('Confirmação de e-mail', () => {
  test('login com e-mail pendente oferece o reenvio do link', async ({ page, api }) => {
    api.on('POST', '/auth/login', () =>
      fail(403, 'Confirme seu e-mail para entrar.', { codigo: 'email_nao_verificado' }),
    )
    await open(page, '/login')
    await page.getByLabel(t('login.email')).fill('maria@beready.test')
    await page.getByRole('textbox', { name: t('login.password'), exact: true }).fill('senhaCerta1')
    await page.getByRole('button', { name: t('common.entrar'), exact: true }).click()

    await expect(page.getByText(t('verificarEmail.loginPendente'))).toBeVisible()
    const reenvio = api.waitFor('POST', '/auth/resend-verification')
    await page.getByRole('button', { name: t('verificarEmail.reenviar') }).click()
    expect((await reenvio).postDataJSON()).toEqual({ email: 'maria@beready.test' })
  })

  test('link válido confirma e leva ao login', async ({ page, api }) => {
    const confirmacao = api.waitFor('POST', '/auth/verify-email')
    await open(page, '/verificar-email/token-valido')
    expect((await confirmacao).postDataJSON()).toEqual({ token: 'token-valido' })

    await expect(page.getByText(t('verificarEmail.confirmado'))).toBeVisible()
    await page.getByRole('link', { name: t('verificarEmail.irParaLogin') }).click()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('link inválido permite pedir outro', async ({ page, api }) => {
    await open(page, '/verificar-email/expirado')

    await expect(page.getByText(t('verificarEmail.linkInvalido'))).toBeVisible()
    await page.getByLabel(t('login.email')).fill('maria@beready.test')
    const reenvio = api.waitFor('POST', '/auth/resend-verification')
    await page.getByRole('button', { name: t('verificarEmail.reenviar') }).click()
    expect((await reenvio).postDataJSON()).toEqual({ email: 'maria@beready.test' })
  })
})

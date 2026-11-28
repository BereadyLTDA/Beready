# Deploy no Render

Passo a passo do item 1 do [roadmap do TCC](roadmap-tcc.md), usando o [`render.yaml`](../render.yaml)
da raiz do repositório. O Blueprint cria a API, o cron do lembrete e o frontend de uma vez; você
só preenche as variáveis secretas.

> O `render.yaml` ainda não foi testado num deploy real. Se o Render reclamar de algum campo na
> criação do Blueprint, a mensagem diz qual é; ajuste e mande o erro para o Claude.

## Custos

| Serviço | Plano | Custo |
|---|---|---|
| `beready-api` | Free | Grátis. Dorme depois de 15 min sem acesso; o primeiro acesso depois disso demora cerca de 1 minuto. |
| `beready-lembretes` | Starter | Cron job não tem plano grátis no Render; cobra pelo tempo de execução (centavos por mês, rodando 1 vez por dia). |
| `beready-web` | Static | Grátis |
| Banco | Supabase (o atual) | Grátis |

Para a banca e para a validação com usuários, considere o plano Starter da API (sem dormir), ao
menos nas semanas em que alguém vai usar. Para economizar, apague o `beready-lembretes` do
`render.yaml`: o app funciona igual, só não manda o lembrete por e-mail.

## 1. Antes de tudo: troque a senha do banco

A senha antiga do Supabase está no histórico público do git.

1. Supabase > Project Settings > Database > Reset database password.
2. Atualize o `DATABASE_URL` no seu `backend/.env` local.
3. Use a string de conexão do **pooler** (porta 6543), a mesma do `.env` atual, com a senha nova.

## 2. Gere os segredos

Na pasta `backend`:

```bash
php artisan key:generate --show    # APP_KEY (copie inteiro, com o "base64:")
```

O `JWT_SECRET` o Render gera sozinho.

## 3. Crie o Blueprint

1. Em [dashboard.render.com](https://dashboard.render.com): New > Blueprint.
2. Conecte o GitHub e escolha `BereadyLTDA/Beready`, branch `main`.
3. O Render lê o `render.yaml` e pede as variáveis marcadas como `sync: false`. As URLs ainda não
   existem nessa hora: os nomes padrão são `https://beready-api.onrender.com` e
   `https://beready-web.onrender.com`. Se o Render avisar que o nome está ocupado, ele acrescenta
   um sufixo; corrija as URLs depois em Environment.

| Variável | Valor |
|---|---|
| `APP_KEY` | O valor do passo 2 |
| `APP_URL` | `https://beready-api.onrender.com` |
| `APP_BASE_URL` | `https://beready-web.onrender.com/` (com a barra) |
| `CORS_ALLOWED_ORIGINS` | `https://beready-web.onrender.com` (sem a barra) |
| `DATABASE_URL` | A do Supabase com a senha nova |
| `EMAIL_*` | Os do SMTP. No Gmail, use uma senha de app. |
| `RECAPTCHA_SECRET_KEY` | Sem ela o login por e-mail e senha é recusado |
| `STRIPE_SECRET_KEY` | `sk_test_...` (modo de teste) |
| `STRIPE_WEBHOOK_SECRET` | Deixe em branco agora; preencha no passo 6 |
| `ANTHROPIC_API_KEY` | Sem ela, só a geração por IA fica fora |
| `CLOUDINARY_*` | Sem elas, só a troca de foto fica fora |
| `GOOGLE_*` | Opcional. `GOOGLE_REDIRECT_URI` = `https://beready-api.onrender.com/social-auth/callback/google` |
| `SENTRY_LARAVEL_DSN` | Opcional |
| `VITE_API_URL` | `https://beready-api.onrender.com` |
| `VITE_RECAPTCHA_SITE_KEY` | A chave de site do reCAPTCHA |
| `VITE_STRIPE_PUBLIC_KEY` | `pk_test_...` |
| `VITE_CONTATO_EMAIL` | O e-mail de contato da LGPD (aparece em `/privacidade`) |

4. Apply. O primeiro build da API leva alguns minutos. Ao subir, o container roda as migrations.

Facebook e LinkedIn não estão no `render.yaml`. Para usar, adicione `FACEBOOK_*` e `LINKEDIN_*`
no grupo `beready-api` (Environment Groups) com os callbacks
`https://beready-api.onrender.com/social-auth/callback/{facebook|linkedin}`.

**Se você mudar alguma `VITE_*` depois**, faça um novo deploy do `beready-web`: essas variáveis
entram no build, não são lidas em tempo de execução.

## 4. reCAPTCHA

Em [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin), na chave do projeto,
adicione o domínio `beready-web.onrender.com` (ou o seu domínio).

## 5. Login social

No console de cada provedor, cadastre o callback
`https://beready-api.onrender.com/social-auth/callback/google` (e os de Facebook e LinkedIn, se
usar).

## 6. Stripe (modo de teste)

1. Stripe > Developers > Webhooks > Add endpoint: `https://beready-api.onrender.com/payments/webhook`.
2. Eventos: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`,
   `customer.subscription.updated` e `customer.subscription.deleted`.
3. Copie o `whsec_...` para `STRIPE_WEBHOOK_SECRET` no grupo `beready-api` e faça um novo deploy
   da API.

## 7. GitHub

1. Settings > Secrets and variables > Actions: troque o secret `VITE_API_URL` pela URL real.
2. Settings > Branches: proteja a `main`, exigindo o CI verde antes do merge.

## 8. Domínio próprio (opcional)

Em cada serviço: Settings > Custom Domains. Depois troque as URLs de todas as variáveis acima
(API, frontend, CORS, callbacks, webhook, reCAPTCHA). Com API e frontend no mesmo domínio (ex.:
`api.beready.app` e `beready.app`), dá para voltar `REFRESH_COOKIE_SAMESITE` para `Lax`.

## 9. Teste de fumaça

- [ ] `https://beready-api.onrender.com/` responde `{"success":true,"message":"API funcionando!",...}`
- [ ] O frontend abre, e recarregar a página em `/login` não dá 404
- [ ] Cadastro, e-mail de confirmação e login
- [ ] Recarregar a página logado mantém a sessão (cookie do refresh token)
- [ ] Login social
- [ ] Assinatura com o cartão `4242 4242 4242 4242` e o plano ativado pelo webhook
- [ ] Geração por IA
- [ ] Exercício de pronúncia (precisa do HTTPS)
- [ ] Cron: em `beready-lembretes`, Trigger Run, e o log mostra "N lembrete(s) enviado(s)"

Quando tudo passar, ponha os links no README e marque o item 1 como pronto no roadmap.

## Problemas comuns

| Sintoma | Causa provável |
|---|---|
| A API não sobe e o log fala de `APP_KEY` ou `cipher` | `APP_KEY` sem o prefixo `base64:` |
| `SQLSTATE[08006]` no log | `DATABASE_URL` errada ou senha antiga |
| O navegador acusa CORS | `CORS_ALLOWED_ORIGINS` diferente da URL do frontend (barra no fim, http em vez de https) |
| Login funciona, mas recarregar a página desloga | `REFRESH_COOKIE_SAMESITE=None` e `REFRESH_COOKIE_SECURE=true` faltando |
| "reCAPTCHA inválido" | Domínio não cadastrado no console do reCAPTCHA, ou chaves de projetos diferentes |
| Frontend chama `localhost:8765` | `VITE_API_URL` não estava definida no build; corrija e faça novo deploy do `beready-web` |

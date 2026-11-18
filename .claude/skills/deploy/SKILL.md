---
name: deploy
description: Como gerar e publicar as imagens de produção do BeReady (Dockerfile.backend com Laravel Octane + FrankenPHP e Dockerfile.frontend com Nginx) e quais variáveis de ambiente são obrigatórias em cada uma. Use sempre que o pedido envolver colocar o projeto no ar, build de imagem de produção, configurar servidor/host (Render, Railway, Fly, VPS, Kubernetes), revisar variáveis de ambiente de produção, ou investigar a API que não sobe em produção, mesmo que não use a palavra "deploy".
---

# Deploy do BeReady

São duas imagens independentes, ambas geradas a partir da raiz do repositório (o contexto do
build precisa enxergar `backend/`, `frontend/` e `docker/`).

## Backend (API)

```bash
docker build -f Dockerfile.backend --target prod -t beready-api .
docker run -p 8765:8765 --env-file backend/.env.production beready-api
```

O estágio `prod` instala as dependências sem as de desenvolvimento, liga o opcache e sobe o
**Laravel Octane sobre o FrankenPHP** na porta 8765. Ao iniciar, `docker/entrypoint.sh` roda
`config:cache`, `route:cache` e `migrate --force`. Em um banco que veio da versão em CakePHP, a
migration inicial não recria nada, só se registra como aplicada.

### Variáveis obrigatórias

| Variável | Por quê |
|---|---|
| `APP_KEY` | Chave do Laravel. Gere uma vez com `php artisan key:generate --show` e guarde como segredo. |
| `APP_ENV=production` e `APP_DEBUG=false` | Com debug ligado, os erros 500 mostram arquivo e linha. |
| `APP_BASE_URL` | URL do frontend; destino dos redirecionamentos do login social e dos links de e-mail. |
| `DATABASE_URL` | Postgres de produção (`postgres://usuario:senha@host:5432/banco`). |
| `JWT_SECRET` | Assina os tokens; trocar desloga todo mundo. |
| `CORS_ALLOWED_ORIGINS` | Domínio(s) do frontend, separados por vírgula. Sem isso o navegador bloqueia a API. |
| `REFRESH_COOKIE_SECURE=true` | Produção é HTTPS. Com frontend em outro domínio, também `REFRESH_COOKIE_SAMESITE=None`. |
| `REDIS_URL` | Obrigatória com mais de uma instância/réplica da API (rate limit e login social compartilhados). |

### Variáveis de cada funcionalidade

Sem elas a API sobe, mas a funcionalidade correspondente fica fora:

- Pagamentos: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` (cadastre o webhook em
  `https://<api>/payments/webhook` no painel do Stripe).
- Login: `RECAPTCHA_SECRET_KEY` (sem ela o login por e-mail e senha é recusado).
- Login social: `GOOGLE_*`, `FACEBOOK_*`, `LINKEDIN_*` (`CLIENT_ID`, `CLIENT_SECRET`,
  `REDIRECT_URI` apontando para `https://<api>/social-auth/callback/<provedor>`).
- E-mail: `EMAIL_HOST`, `EMAIL_PORT`, `EMAIL_USERNAME`, `EMAIL_PASSWORD`, `EMAIL_FROM`, `EMAIL_FROM_NAME`.
- Fotos de perfil: `CLOUDINARY_*`.
- IA: `ANTHROPIC_API_KEY` (opcional: `ANTHROPIC_MODEL`, `IA_LIMITE_DIARIO`).
- Monitoramento: `SENTRY_LARAVEL_DSN`.

A lista completa, com comentários, está em `backend/.env.example`.

## Frontend

```bash
docker build -f Dockerfile.frontend --target prod \
  --build-arg VITE_API_URL=https://api.exemplo.com \
  --build-arg VITE_RECAPTCHA_SITE_KEY=... \
  --build-arg VITE_STRIPE_PUBLIC_KEY=... \
  -t beready-web .
docker run -p 8080:80 beready-web
```

As variáveis `VITE_*` são **embutidas no bundle no momento do build** (não são lidas em tempo de
execução). Mudou a URL da API? Gere a imagem de novo. Pelo mesmo motivo, nunca coloque segredo
em variável `VITE_*`: tudo nelas fica público no JavaScript.

O Nginx (`docker/nginx.conf`) serve a SPA, manda as rotas do vue-router para o `index.html` e
cacheia para sempre os arquivos de `/assets/` (têm hash no nome).

## Checklist antes de publicar

1. Testes verdes (skill `rodar-testes`) e CI verde no GitHub.
2. Backup do banco antes da primeira subida (o entrypoint roda as migrations).
3. Variáveis obrigatórias preenchidas no provedor (nunca versione `.env.production`).
4. Depois de subir: `GET https://<api>/` responde `{"success":true,"message":"API funcionando!"}`,
   e o login e uma listagem funcionam no frontend publicado.

## Quando a API não sobe

- Erro de `APP_KEY` → falta a variável.
- Erro de conexão com o banco no entrypoint → `DATABASE_URL` errada ou o banco não aceita conexões
  externas (no Supabase, use a string de conexão do pooler).
- O navegador acusa CORS → confira `CORS_ALLOWED_ORIGINS` (domínio exato, com `https://`, sem barra no fim).
- Login funciona e cai em seguida → cookie de refresh bloqueado: `REFRESH_COOKIE_SECURE=true` e,
  se front e API estão em domínios diferentes, `REFRESH_COOKIE_SAMESITE=None`.

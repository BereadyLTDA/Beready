# Arquitetura

Base: `docs/arquitetura.md`, `Dockerfile.backend`, `Dockerfile.frontend`, `docker/entrypoint.sh`,
`backend/config/beready.php`, `backend/composer.json` e a seção 1 de `docs/roadmap-tcc.md`.

## Diagrama de componentes

O frontend é uma SPA em Vue 3 (Pinia, Vue Router, vue-i18n e axios) que conversa com a API REST
em Laravel 13 por HTTP/JSON. A API roda sobre o Laravel Octane com o servidor FrankenPHP. Dentro
da API, cada requisição passa pelos middlewares (`LimitaRequisicoes`, `AutenticaJwt`,
`ApenasAdmin`, `LimiteDoPlano`), depois controller, service e model Eloquent.

Os serviços externos ficam atrás de interfaces em `backend/app/Contracts/`
(`StripeGatewayInterface`, `GeradorTextoInterface`, `CloudinaryServiceInterface`), o que permite
trocá-los por implementações falsas nos testes.

```mermaid
flowchart LR
    subgraph navegador["Navegador do aluno"]
        spa["SPA Vue 3<br/>Pinia, Vue Router, vue-i18n"]
        axiosApi["core/services/api.ts<br/>axios com interceptor JWT"]
        webspeech["Web Speech API<br/>speechSynthesis e reconhecimento de voz"]
        recaptchaJs["reCAPTCHA v3<br/>script do Google"]
        spa --> axiosApi
        spa --> webspeech
        spa --> recaptchaJs
    end

    subgraph api["API Laravel 13 sobre Octane + FrankenPHP"]
        mw["Middlewares<br/>LimitaRequisicoes, AutenticaJwt,<br/>ApenasAdmin, LimiteDoPlano"]
        ctrl["Controllers<br/>UsersController, PlanosController,<br/>PaymentsController, FlashcardsController..."]
        svc["Services<br/>JwtService, UserService, AssinaturaService,<br/>GeracaoService, RepeticaoEspacadaService..."]
        gw["Adaptadores externos<br/>StripeGateway, ClaudeGeradorTexto,<br/>CloudinaryService, Socialite"]
        mail["Mailables<br/>VerificarEmail, BoasVindas,<br/>RecuperarSenha, LembreteEstudo"]
        sched["Scheduler<br/>beready:lembretes às 18h"]
        orm["Models Eloquent"]
        mw --> ctrl --> svc
        svc --> gw
        svc --> mail
        svc --> orm
        sched --> svc
    end

    db[("PostgreSQL<br/>Supabase")]
    redis[("Redis opcional<br/>cache, rate limit,<br/>revogação de tokens")]
    stripe["Stripe<br/>Checkout, assinaturas, webhook"]
    anthropic["Anthropic<br/>API Messages do Claude"]
    cloudinary["Cloudinary<br/>fotos de perfil"]
    oauth["OAuth<br/>Google, Facebook, LinkedIn"]
    smtp["Servidor SMTP<br/>e-mails transacionais"]
    sentry["Sentry<br/>erros da API, opcional"]
    recaptchaApi["Google reCAPTCHA<br/>siteverify"]

    axiosApi -- "HTTPS JSON + Bearer<br/>cookie refresh_token" --> mw
    spa -- "redireciona para o Checkout" --> stripe
    stripe -- "POST /payments/webhook" --> mw
    spa -- "redireciona para o login social" --> oauth
    oauth -- "GET /social-auth/callback/{provider}" --> mw

    orm --> db
    mw --> redis
    svc --> redis
    gw --> stripe
    gw --> anthropic
    gw --> cloudinary
    gw --> oauth
    mail --> smtp
    ctrl --> recaptchaApi
    api -.-> sentry
```

Observações:

- Sem `REDIS_URL`, o cache fica em arquivo (`config/cache.php`), o que só funciona com uma instância
  da API. Com mais de uma instância, o Redis é obrigatório, porque guarda os contadores do rate
  limit e da IA, a lista de tokens revogados e os códigos do login social.
- A síntese e o reconhecimento de voz rodam no navegador. O servidor só recebe a frase esperada e
  o texto reconhecido, e calcula a nota (`PronunciaService`).
- O reCAPTCHA é gerado no navegador e conferido pela API no cadastro e no login
  (`UsersController::checarRecaptcha`).

## Diagrama de implantação

Arquitetura da seção 1 do roadmap, como descrita no Blueprint do Render (`render.yaml`) e em
`docs/deploy-render.md`. O Railway é a alternativa citada no roadmap; o Blueprint cobre só o
Render. A API sobe com a imagem `Dockerfile.backend --target prod`. O `docker/entrypoint.sh` roda
`config:cache`, `route:cache` e `migrate --force` antes do Octane. O frontend é publicado como site
estático a partir de `frontend/dist`.

No `render.yaml`, o lembrete roda como um Cron Job próprio (`beready-lembretes`), com a mesma
imagem e o grupo de variáveis da API, chamando `php artisan beready:lembretes` uma vez por dia às
21h UTC (18h em Brasília). Fora do Render, a alternativa é um cron chamando
`php artisan schedule:run` a cada minuto, que dispara o mesmo comando pelo agendador do Laravel.

[preencher: URLs públicas da API e do frontend depois do deploy.]

```mermaid
flowchart TB
    usuario["Navegador do usuário"]

    subgraph render["Render, Blueprint render.yaml"]
        subgraph estatico["Static Site beready-web"]
            dist["frontend/dist<br/>npm ci e npm run build<br/>rewrite da SPA para index.html"]
        end
        subgraph web["Web Service beready-api, Docker"]
            subgraph imagem["Imagem Dockerfile.backend, target prod<br/>base dunglas/frankenphp:1-php8.4"]
                entry["beready-entrypoint<br/>config:cache, route:cache, migrate --force"]
                octane["php artisan octane:frankenphp<br/>porta 8765"]
                entry --> octane
            end
        end
        cron["Cron Job beready-lembretes, Docker<br/>php artisan beready:lembretes<br/>0 21 * * * UTC, mesmas variáveis da API"]
    end

    subgraph supabase["Supabase"]
        pg[("PostgreSQL")]
    end

    subgraph upstash["Upstash, plano grátis"]
        redisU[("Redis<br/>só com mais de uma instância")]
    end

    subgraph externos["Serviços externos"]
        stripeE["Stripe, modo de teste"]
        anthropicE["Anthropic"]
        cloudinaryE["Cloudinary"]
        oauthE["Google, Facebook, LinkedIn"]
        smtpE["Gmail SMTP ou Resend/Brevo"]
        sentryE["Sentry"]
        recaptchaE["Google reCAPTCHA"]
    end

    usuario -- "HTTPS" --> dist
    usuario -- "HTTPS, API_URL" --> octane
    octane --> pg
    cron --> pg
    octane -.-> redisU
    octane --> stripeE
    stripeE -- "webhook" --> octane
    octane --> anthropicE
    octane --> cloudinaryE
    octane --> oauthE
    octane --> smtpE
    cron --> smtpE
    octane -.-> sentryE
    octane --> recaptchaE
```

Variáveis de produção que mudam em relação ao ambiente local (seção 1 do roadmap):
`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` e `JWT_SECRET` novos, `APP_URL`,
`APP_BASE_URL`, `CORS_ALLOWED_ORIGINS` só com a URL do frontend, `REFRESH_COOKIE_SECURE=true`,
`REFRESH_COOKIE_SAMESITE=None` quando frontend e API estão em domínios diferentes,
`TRUSTED_PROXIES=*`, `REDIS_URL` (se houver mais de uma instância) e `SENTRY_LARAVEL_DSN`
(opcional). No frontend, o build recebe `VITE_API_URL` e `VITE_RECAPTCHA_SITE_KEY`.

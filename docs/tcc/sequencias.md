# Diagramas de sequência

Base: `backend/routes/api.php`, `backend/app/Http/Controllers/UsersController.php`,
`PlanosController.php`, `PaymentsController.php`, `TraducoesController.php`,
`backend/app/Services/` (`JwtService`, `UserService`, `AssinaturaService`, `GeracaoService`,
`ClaudeGeradorTexto`), `backend/app/Http/Middleware/AutenticaJwt.php`,
`frontend/src/core/services/api.ts`, `frontend/src/stores/auth.ts`,
`frontend/src/stores/permissionStore.ts` e as telas em `frontend/src/modules/auth/views/`.

Todas as requisições passam antes pelo `LimitaRequisicoes` (300 por minuto por IP e 10 por minuto
nas rotas `/auth/*`). Para não repetir, os diagramas só mostram esse middleware onde ele importa.

## 1. Login com refresh token

O access token (JWT, 1 hora) fica só em memória, no store `auth` do Pinia. O refresh token (JWT,
7 dias, com `jti` aleatório) vai só no cookie `refresh_token`, com `HttpOnly` e `Path=/auth`; o
JavaScript da página não consegue lê-lo. Valores em `backend/config/beready.php`.

O `POST /auth/refresh` devolve só um access token novo. O refresh token não é trocado a cada uso;
um par novo só é emitido no login e quando o próprio usuário troca a senha. A revogação usa o
cache (Redis em produção): `revogarRefresh` grava o `jti` no logout, e `revogarTodos` grava o
instante a partir do qual tokens mais antigos do usuário deixam de valer (troca de senha,
bloqueio, mudança de role, exclusão da conta).

```mermaid
sequenceDiagram
    autonumber
    actor A as Aluno
    participant L as Login.ts
    participant S as stores/auth.ts
    participant X as core/services/api.ts
    participant R as Google reCAPTCHA
    participant MW as AutenticaJwt
    participant UC as UsersController
    participant US as UserService
    participant J as JwtService
    participant C as Cache ou Redis
    participant DB as PostgreSQL

    rect rgba(128,128,128,0.08)
    Note over A,DB: Login
    A->>L: e-mail e senha
    L->>R: gerarToken('login'), reCAPTCHA v3
    R-->>L: recaptcha_token
    L->>S: login(credentials)
    S->>X: POST /auth/login
    X->>UC: email, password, recaptcha_token
    UC->>R: siteverify com RECAPTCHA_SECRET_KEY
    R-->>UC: success e score, aceita se score maior ou igual a 0.5
    UC->>US: login(email, password)
    US->>DB: busca usuário por e-mail
    DB-->>US: users
    US->>US: password_verify, status ativo, email_verificado_em
    alt credenciais inválidas
        US-->>UC: RuntimeException 401
        UC-->>X: 401
    else e-mail não confirmado
        US-->>UC: EmailNaoVerificadoException
        UC-->>X: 403 com codigo email_nao_verificado
    else ok
        US-->>UC: user
        UC->>J: generateTokens(id, email, nome, role)
        J-->>UC: access_token 1h e refresh_token 7 dias com jti
        UC-->>X: 200 user e tokens.access_token, Set-Cookie refresh_token HttpOnly Path=/auth
        X-->>S: resposta
        S->>S: guarda accessToken só em memória
    end
    end

    rect rgba(128,128,128,0.08)
    Note over A,DB: Requisição autenticada e renovação automática
    S->>X: GET /flashcards, por exemplo
    X->>X: interceptor de request: Authorization Bearer access_token
    X->>MW: requisição com Bearer
    MW->>J: validateToken(token, access)
    J->>C: tokens do usuário revogados até quando?
    alt access token válido
        MW->>MW: define user_id e role no Request
        MW-->>X: 200 vindo do controller da rota
    else access token expirado ou revogado
        MW-->>X: 401 Token inválido ou expirado
        X->>S: interceptor de resposta: refresh(), uma promise compartilhada
        S->>X: POST /auth/refresh, withCredentials envia o cookie
        X->>UC: refresh()
        UC->>J: validateToken(refresh_token, refresh)
        J->>C: revogados_ate do usuário e jti
        C-->>J: não revogado
        UC->>US: getUserById e isActive
        UC->>J: generateAccessToken(user)
        UC-->>X: 200 access_token novo e user
        X-->>S: atualiza accessToken
        X->>MW: repete a requisição original uma vez, com o token novo
        MW-->>X: 200
    else refresh inválido, expirado ou revogado
        UC-->>X: 401 e cookie expirado
        X->>S: logout(callServer false), volta para /login
    end
    end

    rect rgba(128,128,128,0.08)
    Note over A,DB: Recarregar a página
    A->>S: abre o app, init()
    S->>X: POST /auth/refresh
    Note right of S: Sem access token em memória, a sessão é restaurada pelo cookie
    end

    rect rgba(128,128,128,0.08)
    Note over A,DB: Logout
    A->>S: logout()
    S->>X: POST /auth/logout
    X->>UC: logout()
    UC->>J: revogarRefresh(refresh_token)
    J->>C: jwt_refresh_revogado_jti até o exp do token
    UC-->>X: 200 e Set-Cookie refresh_token expirado
    S->>S: limpa os stores e vai para /login
    end
```

## 2. Assinatura com o webhook do Stripe

A ativação de um plano pago só acontece pelo webhook, que é a fonte confiável (o retorno do
navegador para `success_url` não ativa nada). O webhook é público, mas o corpo só é aceito com uma
assinatura `Stripe-Signature` válida para o `STRIPE_WEBHOOK_SECRET`. O Stripe pode reenviar o
mesmo evento, por isso `checkoutConcluido` confere se a sessão ou a subscription já foram
processadas.

```mermaid
sequenceDiagram
    autonumber
    actor A as Aluno
    participant P as PlanosPage.ts
    participant PS as permissionStore
    participant PC as PlanosController
    participant PY as PaymentsController
    participant AS as AssinaturaService
    participant SG as StripeGateway
    participant ST as Stripe
    participant DB as PostgreSQL

    A->>P: escolhe plano e ciclo mensal, anual ou trial
    P->>PS: assinarPlano(planoId, ciclo)
    PS->>PC: POST /planos/{id}/assinar, Bearer
    PC->>AS: getAtiva, jaUsouTrial, getPreco
    alt ciclo inválido, plano inativo, mesmo plano, recorrente ativa ou trial já usado
        PC-->>PS: 400, 404 ou 409
    else preço zero: plano gratuito ou trial
        PC->>AS: ativar(usuario, plano, ciclo)
        AS->>DB: transação: encerra ativas e cria assinatura active
        PC-->>PS: 200 requires_payment false
        P-->>A: plano ativado
    else plano pago
        PC->>SG: criarCustomer, se users.stripe_customer_id estiver vazio
        SG->>ST: cria Customer
        PC->>DB: grava stripe_customer_id
        PC->>SG: criarCheckoutAssinatura, mode subscription, metadata user_id, plan_id, ciclo
        SG->>ST: cria Checkout Session
        ST-->>SG: url
        PC-->>PS: 200 requires_payment true e checkout_url
        P->>A: window.location.href = checkout_url
        A->>ST: paga no Checkout, cartão de teste 4242
        ST-->>A: redireciona para APP_BASE_URL/dashboard?session_id=...
        Note over A,PS: O app recarrega: auth.init() renova a sessão e o permissionStore<br/>busca GET /user/assinatura, AssinaturasController::current

        par webhook, assíncrono
            ST->>PY: POST /payments/webhook, evento checkout.session.completed
            PY->>PY: Webhook::constructEvent com Stripe-Signature e STRIPE_WEBHOOK_SECRET
            alt assinatura inválida
                PY-->>ST: 400
            else válida
                PY->>PY: confere payment_status paid e metadata
                PY->>AS: pagamentoJaProcessado(session.id) ou findPorSubscription
                alt já processado
                    PY-->>ST: 200 received true
                else novo
                    PY->>AS: ativar(usuario, plano, ciclo, session.id, subscription)
                    AS->>DB: transação: encerra ativas e cria assinatura active com stripe_subscription_id
                    PY->>AS: cancelarNoStripe(subscriptions anteriores)
                    AS->>ST: cancela subscriptions duplicadas
                    PY-->>ST: 200 received true
                end
            end
        end
    end

    Note over ST,DB: Eventos seguintes do ciclo de vida
    ST->>PY: invoice.paid
    PY->>AS: registrarRenovacao(subscription, fim do período), atualiza data_fim
    ST->>PY: invoice.payment_failed
    PY->>AS: registrarFalhaPagamento, stripe_status past_due
    ST->>PY: customer.subscription.updated
    PY->>AS: sincronizarSubscription(id, status, cancelar no fim)
    ST->>PY: customer.subscription.deleted
    PY->>AS: encerrarSubscription, volta ao plano gratuito
    Note over PY,ST: Erro inesperado responde 500 para o Stripe reenviar o evento
```

No `customer.subscription.deleted`, o `encerrarSubscription` marca a assinatura como `canceled`,
`is_ativo = false`, grava `data_cancelamento` e, se ela estava ativa, chama
`ativarPlanoGratuito` na mesma transação.

## 3. Geração por IA

A tradução e as frases semelhantes são geradas a partir do texto de um prompt do próprio aluno. O
`GeracaoService` conta a tentativa antes de chamar o provedor, e a tentativa conta mesmo se o
provedor falhar, porque é a chamada que custa. O contador é diário, por usuário, no cache
(`Contador::incrementar`, atômico no Redis). O teto é `IA_LIMITE_DIARIO`, com padrão de 50.

O `ClaudeGeradorTexto` usa a API Messages pelo SDK `anthropic-ai/sdk`, com o modelo de
`ANTHROPIC_MODEL`, saída estruturada em JSON Schema, esforço baixo e fallback do servidor.

```mermaid
sequenceDiagram
    autonumber
    actor A as Aluno
    participant T as useTraducoes.ts
    participant TS as traducaoService.ts
    participant MW as AutenticaJwt
    participant TC as TraducoesController
    participant G as GeracaoService
    participant CT as Contador
    participant C as Cache ou Redis
    participant CG as ClaudeGeradorTexto
    participant AN as Anthropic API
    participant DB as PostgreSQL

    A->>T: gerarTraducao(promptId, idiomaDestino)
    T->>TS: gerar(promptId, idiomaDestino)
    TS->>MW: POST /prompts/{id}/traducoes/gerar, Bearer
    MW->>TC: user_id
    TC->>DB: promptDoUsuario(id), confere o dono
    alt prompt não existe ou é de outro usuário
        TC-->>TS: 404
    else prompt do aluno
        TC->>G: gerarTraducao(prompt, usuarioId, idioma_destino)
        G->>G: valida texto até 2000 caracteres e idioma_destino
        G->>CT: incrementar(ia_uso_uID_AAAAMMDD, 86400 s)
        CT->>C: add e increment atômico
        C-->>CT: total do dia
        alt total acima do limite diário
            G-->>TC: GeracaoException limiteDiario
            TC-->>TS: 429
        else dentro do limite
            G->>CG: traduzir(texto, idioma origem, destino, contexto)
            CG->>AN: beta.messages.create, output_config effort low e json_schema
            alt chave ausente ou recusada pela Anthropic
                CG-->>G: GeracaoException naoConfigurada
                G-->>TC: 503
            else 429, 5xx ou falha de conexão
                CG-->>G: GeracaoException indisponivel
                G-->>TC: 503
            else stop_reason refusal
                CG-->>G: GeracaoException recusada
                G-->>TC: 422
            else resposta incompleta ou sem JSON
                CG-->>G: GeracaoException falhou
                G-->>TC: 502
            else end_turn com JSON válido
                AN-->>CG: texto_traduzido, pontuacao_confianca, traducoes_alternativas
                CG-->>G: dados
                G->>DB: Traducao::create, servico_traducao claude
                G-->>TC: tradução
                TC-->>TS: 201 tradução
            end
        end
    end
    TS-->>T: resposta ou erro com a mensagem da API
    T-->>A: mostra a tradução ou a mensagem
```

As frases semelhantes seguem o mesmo caminho: `POST /prompts/{id}/frases/gerar` com
`quantidade` de 1 a 10 (padrão 5), `FrasesSemelhantesController`, `GeracaoService::gerarFrases`,
`ClaudeGeradorTexto::frasesSemelhantes` e uma linha em `frases_semelhantes` por frase.

## 4. Verificação de e-mail

O token é aleatório (32 bytes em hexadecimal), vale 24 horas e só o hash SHA-256 fica em
`users.verificacao_token`. O link aponta para o frontend (`APP_BASE_URL` +
`verificar-email/{token}`), que chama a API. O reenvio responde sempre a mesma mensagem, para não
revelar quais e-mails têm conta.

```mermaid
sequenceDiagram
    autonumber
    actor V as Visitante
    participant RG as Register.ts
    participant VE as VerificarEmail.vue
    participant UC as UsersController
    participant US as UserService
    participant AS as AssinaturaService
    participant DB as PostgreSQL
    participant M as Servidor SMTP

    V->>RG: nome, e-mail, senha e aceite dos termos
    RG->>RG: valida o formulário, aceite obrigatório
    RG->>UC: POST /auth/register com recaptcha_token
    UC->>UC: checarRecaptcha
    UC->>US: register(data)
    US->>US: e-mail válido, aceite_termos, senha com 6 ou mais caracteres, e-mail livre
    US->>DB: cria users com senha_hash, termos_versao e termos_aceitos_em
    US->>US: enviarVerificacao: token = bin2hex(random_bytes(32))
    US->>DB: verificacao_token = sha256(token), expira em 24 h
    US->>M: Mailable VerificarEmail com o link
    UC->>AS: ativarPlanoGratuito(usuario)
    UC-->>RG: 201 Conta criada. Enviamos um link
    RG-->>V: pede para abrir o e-mail

    V->>VE: abre /verificar-email/:token
    VE->>UC: POST /auth/verify-email com token
    UC->>US: verificarEmail(token)
    US->>DB: busca por sha256(token) com verificacao_token_expira no futuro
    alt não encontrado ou expirado
        UC-->>VE: 400 Link de confirmação inválido ou expirado
        VE-->>V: oferece reenviar o link
        V->>VE: informa o e-mail
        VE->>UC: POST /auth/resend-verification
        UC->>US: reenviarVerificacao(email)
        Note right of US: Conta inexistente ou já confirmada: não faz nada
        UC-->>VE: 200 sempre com a mesma mensagem
    else válido
        US->>DB: email_verificado_em = agora, limpa token e validade
        US->>M: Mailable BoasVindas, só na primeira confirmação
        UC-->>VE: 200 E-mail confirmado
        VE-->>V: segue para o login
    end

    Note over V,M: Login antes de confirmar: UserService::login lança EmailNaoVerificadoException,<br/>a API responde 403 com codigo email_nao_verificado e Login.ts oferece o reenvio
```

Contas criadas antes desta funcionalidade foram marcadas como verificadas pela migration
`2026_11_19_000000_adiciona_verificacao_de_email.php`, para ninguém perder o acesso.

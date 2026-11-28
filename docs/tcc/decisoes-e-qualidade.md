# Decisões técnicas e qualidade (rascunho)

Rascunho dos tópicos 5 (decisões técnicas), 6 (qualidade) e 7 (trabalhos futuros) da seção 7 de
`docs/roadmap-tcc.md`. Os fatos vêm do código e do histórico do git. Onde um número não pôde ser
verificado aqui, há um marcador `[preencher: ...]`.

Contagens feitas em 28/11/2026, na `main` no commit `57aed6e`. Refaça as contagens antes da entrega:

```sh
grep -rE "public function test" backend/tests --include=*.php | wc -l   # PHPUnit
grep -rE "#\[Test\]" backend/tests --include=*.php | wc -l             # PHPUnit com atributo
cd frontend && npx playwright test --list | tail -1                     # Playwright
cd frontend && npx vitest list | wc -l                                  # Vitest
```

## 1. Decisões técnicas

### 1.1 Migração do backend de CakePHP 5 para Laravel 13

**Contexto.** O backend começou em CakePHP 5. Até meados de novembro de 2026 ele já tinha a API
protegida com JWT e as assinaturas no Stripe (`98d9bc1`, 06/10/2026), a repetição espaçada
(`8ce6a6c`, 07/10/2026), testes de integração em Postgres no CI (`e3b9d0b`) e, por fim, injeção de
dependências, o contrato OpenAPI e a geração de traduções com IA (`7c29079`, 16/11/2026).

**Decisão.** Migrar a API para Laravel 13 no commit `a58514f`
(`refactor(backend)!: migra a API de CakePHP 5 para Laravel 13`, 18/11/2026).

**Motivos** (os listados no roadmap; desenvolva no texto):

- ecossistema maior de pacotes oficiais;
- Laravel Octane, que mantém a aplicação carregada em memória entre requisições (aqui sobre o
  FrankenPHP);
- Laravel Socialite para o login com Google, Facebook e LinkedIn;
- comunidade e documentação maiores.

**Como a paridade foi garantida.** Pela mensagem do commit `a58514f`:

- mesmas URLs, mesmo JSON, mesmo banco e mesmos nomes de variáveis de ambiente, sem mudança no
  frontend;
- o JSON de cada resposta dos testes é validado contra o `backend/openapi.yaml`
  (`Tests\ApiTestCase`), e o `Tests\Feature\OpenApiContractTest` falha se uma rota existir sem
  operação no contrato, ou o contrário;
- os testes foram portados: 180, incluindo o do contrato OpenAPI;
- em um banco vindo do CakePHP, a migration `0001_01_01_000000_esquema_inicial` só se registra
  como aplicada, sem recriar nada (`Schema::hasTable('users')`).

**Números** (do `git show --shortstat a58514f` e da mensagem do commit):

| Item | Valor |
|---|---|
| Arquivos alterados no commit da migração | 336 |
| Linhas adicionadas / removidas | 16.085 / 21.533 |
| Rotas em `routes/api.php` no momento da migração | 118 |
| Testes portados | 180 |
| Análise estática | Larastan nível 6, com baseline |
| Testes PHPUnit hoje (`public function test` em `backend/tests`) | 230, em 22 arquivos |
| Testes com o atributo `#[Test]` | 0 |

O crescimento de 180 para 230 veio das funcionalidades posteriores à migração (verificação de
e-mail, pronúncia, gamificação, nivelamento, gráficos, LGPD e a exportação das métricas da
validação com usuários).

[preencher: tempo de resposta ou uso de memória antes e depois da migração, se houver medição. Não
há esse dado no repositório.]

### 1.2 JWT com refresh token em cookie httpOnly

Arquivos: `backend/app/Services/JwtService.php`, `UsersController.php`,
`Http/Middleware/AutenticaJwt.php`, `frontend/src/core/services/api.ts`,
`frontend/src/stores/auth.ts`. Fluxo completo em `sequencias.md`.

- **Dois tokens.** Access token JWT de 1 hora, enviado no header `Authorization: Bearer`.
  Refresh token JWT de 7 dias, com `jti` aleatório, enviado só no cookie `refresh_token`
  (`HttpOnly`, `Path=/auth`, `Secure` e `SameSite` configuráveis por `REFRESH_COOKIE_SECURE` e
  `REFRESH_COOKIE_SAMESITE`). Os dois são assinados com `JWT_SECRET` e têm um campo `type` que
  impede usar um no lugar do outro.
- **Access token só em memória.** O frontend não grava o token em `localStorage`, então um script
  injetado na página (XSS) não encontra um token de longa duração para levar. O refresh token não
  é acessível ao JavaScript.
- **Renovação transparente.** O interceptor do axios, ao receber 401, faz um único
  `POST /auth/refresh` compartilhado entre as requisições que falharam ao mesmo tempo e repete a
  requisição original uma vez. Ao recarregar a página, `auth.init()` restaura a sessão pelo
  cookie.
- **Revogação.** Feita no cache (Redis em produção, para valer em todas as instâncias):
  - logout: o `jti` do refresh token entra numa lista até a data em que ele expiraria;
  - troca de senha, bloqueio, mudança de role pelo `PUT /users/update/{id}` e exclusão da conta:
    `revogarTodos` invalida todos os tokens do usuário emitidos antes daquele instante (com
    precisão de milissegundos, campo `iatm`). Quem trocou a própria senha recebe um par novo e
    continua logado.
- **O que não foi feito:** o refresh token não é rotacionado a cada `POST /auth/refresh`. Ver
  trabalhos futuros.

### 1.3 Repetição espaçada (SM-2 simplificado)

Arquivo: `backend/app/Services/RepeticaoEspacadaService.php`. A classe não acessa o banco: recebe o
estado do flashcard e a avaliação e devolve o próximo estado, que o `FlashcardService` grava nas
colunas `repeticoes`, `intervalo_dias`, `fator_ease`, `ultima_revisao` e `proxima_revisao`. Cada
revisão também gera uma linha em `respostas_usuario` (tipo `flashcard`).

O aluno avalia cada card com três botões, mapeados para a nota `q` do SM-2:

| Avaliação | Nota q |
|---|---|
| `errei` | 1 |
| `bom` | 4 |
| `facil` | 5 |

Algoritmo:

1. Atualiza o fator de facilidade (EF) a cada revisão, inclusive no erro:
   `EF' = EF + (0,1 − (5 − q) × (0,08 + (5 − q) × 0,02))`, com mínimo 1,3 e arredondado em duas
   casas. O EF inicial é 2,5. Na prática: `errei` tira 0,54, `bom` mantém, `facil` soma 0,1.
2. Se `q < 3` (errei): `repeticoes = 0`, `intervalo_dias = 0` e o card volta em 10 minutos.
3. Se acertou: `repeticoes` aumenta em 1 e o intervalo é
   - 1 dia na 1ª repetição;
   - 6 dias na 2ª;
   - depois, `round(intervalo anterior × EF)`, no mínimo 1 dia.
4. Com intervalo em dias, a próxima revisão vence às 00:00 do dia de destino, para o card contar
   como "para revisar hoje" o dia inteiro.

Diferenças em relação ao SM-2 original: só três notas (1, 4 e 5) em vez de seis, o retorno em 10
minutos no erro e o vencimento no início do dia. `GET /flashcards/revisao` lista os cards com
`proxima_revisao` vencida.

### 1.4 Revisão de segurança

Commits principais: `98d9bc1` (06/10/2026, protege a API), `5d8e02c` e `46fd1f8` (19/11/2026).

- **Revogação de tokens** no logout, na troca de senha, no bloqueio e na exclusão (seção 1.2).
  Antes de `5d8e02c`, apagar o cookie no logout não invalidava uma cópia do refresh token.
- **Senha atual** exigida para trocar a senha, e senha exigida para excluir a própria conta: um
  access token roubado não basta para tomar ou apagar a conta.
- **Verificação de e-mail** no cadastro: login bloqueado (403, `codigo email_nao_verificado`) até
  a confirmação. Tokens de verificação e de recuperação de senha guardados só como hash SHA-256,
  com validade. O reenvio não revela se o e-mail tem conta.
- **reCAPTCHA v3** no cadastro e no login, conferido no servidor (score mínimo 0,5). Se o Google
  não responder, a API devolve 503 sem detalhes internos.
- **Rate limit** por IP (`LimitaRequisicoes`): 300 requisições por minuto, 10 por minuto nas rotas
  de autenticação. Contadores atômicos no Redis quando há mais de uma instância.
- **Autorização:** `/admin/*` exige role `admin` lida do banco (`ApenasAdmin`), não do token. Os
  controllers conferem o dono de cada recurso. Limites do plano aplicados no servidor
  (`LimiteDoPlano`). Correção de quizzes e do nivelamento e nota da pronúncia sempre no servidor.
  O commit `46fd1f8` removeu endpoints que deixavam o cliente inflar o próprio progresso.
- **Stripe:** webhook aceito só com `Stripe-Signature` válida, processamento idempotente, e
  exclusão da conta cancelando as assinaturas no Stripe para não continuar cobrando.
- **Campos protegidos:** `role`, `status`, `senha_hash`, `stripe_customer_id` e os campos do Stripe
  ficam fora do `$fillable` dos models e só são gravados em pontos específicos.
- **CORS** restrito a `CORS_ALLOWED_ORIGINS`, com credenciais.
- **Custo da IA:** teto diário por usuário (`IA_LIMITE_DIARIO`) contado antes da chamada.

Ponto observado na leitura do código: `POST /admin/users/role` grava a nova role sem chamar
`revogarTodos`, ao contrário do `PUT /users/update/{id}`. O acesso a `/admin/*` não é afetado,
porque o `ApenasAdmin` lê a role no banco, mas o campo `role` do access token antigo fica
desatualizado até ele expirar (no máximo 1 hora).

## 2. Qualidade

### 2.1 Integração contínua

Arquivo: `.github/workflows/ci.yml`. Roda em push para `main`, `master`, `develop` e `feature/**`,
em pull requests e manualmente. Quatro jobs:

| Job | O que faz |
|---|---|
| `testsuite` | PHP 8.4 e um Postgres 15 descartável; roda o PHPUnit (230 testes), que aplica as migrations e os seeds a cada execução e valida cada resposta contra o `openapi.yaml`. |
| `code-quality` | Larastan (PHPStan) nível 6 com baseline, bloqueante para erros novos; Pint em modo `--test`, bloqueante. |
| `frontend` | Node 22; regera os tipos da API a partir do contrato (`npm run gen:api`) e falha se `src/core/types/api.gen.ts` mudar; roda o Vitest (12 testes em 2 arquivos); faz o build. |
| `frontend-e2e` | Playwright com Chromium, API simulada em `frontend/e2e/support/api.ts`; 107 testes em 13 arquivos (`npx playwright test --list`), incluindo acessibilidade com axe e um projeto em viewport de 360 px; publica o relatório como artefato. |

Há também `.github/workflows/stale.yml`, que marca issues e pull requests parados.

[preencher: tempo médio de execução do CI e quantas execuções passaram, se quiser citar. Ver na
aba Actions do GitHub.]

### 2.2 Desempenho (Lighthouse)

Medidas já tomadas, pelos commits `9424246` e `fe18e25`: logo em WebP e no tamanho exibido,
imagens com dimensões declaradas, sem preconnect de fontes não usadas, montagem do app logo após
renovar a sessão. Os gráficos de progresso são SVG próprio, sem biblioteca de gráficos (o
`package.json` só tem axios, pinia, vue, vue-i18n e vue-router como dependências de execução).

| Página | Desempenho | Acessibilidade | Boas práticas | SEO |
|---|---|---|---|---|
| `/` | [preencher] | [preencher] | [preencher] | [preencher] |
| `/login` | [preencher] | [preencher] | [preencher] | [preencher] |
| `/dashboard` | [preencher] | [preencher] | [preencher] | [preencher] |

[preencher: rodar o Lighthouse no ambiente publicado (item 1 do roadmap), em modo anônimo, mobile
e desktop, e anotar a data e a URL. Não há números do Lighthouse no repositório.]

### 2.3 Acessibilidade (meta: WCAG 2.2 AA)

- **Teste automático:** `frontend/e2e/a11y.spec.ts` roda o axe-core com as tags `wcag2a`,
  `wcag2aa`, `wcag21a`, `wcag21aa` e `wcag22aa` em 6 páginas públicas (`/`, `/login`,
  `/register`, `/planos`, `/termos`, `/privacidade`), 13 telas logadas (dashboard, flashcards,
  estudo, revisão, quizzes, edição e jogo de quiz, planos, preferências, pronúncia, conquistas,
  nivelamento e progresso), no painel admin nos temas claro e escuro e no dashboard em tema
  escuro. Violações críticas e sérias reprovam o build; as leves e moderadas ficam registradas no
  relatório.
- **Temas:** claro, escuro e daltônico, com cores vindas dos tokens do design system
  (`ca3cdf8`, `0b54ae3`).
- **Gráficos:** SVG próprio, cada um com uma tabela equivalente para leitores de tela.
- **Pronúncia:** botões com rótulo e resultado anunciado (requisito do item 2 do roadmap).
- **Responsividade:** `frontend/e2e/responsive.spec.ts` em viewport de 360 px.

O axe não cobre todos os critérios da WCAG 2.2 AA (por exemplo, ordem lógica de foco e
compreensão do conteúdo). [preencher: resultado de uma verificação manual com teclado e leitor de
tela (NVDA ou similar), se for feita.]

### 2.4 Internacionalização

- `vue-i18n`, com 14 idiomas em `frontend/src/locales/`: alemão (`de`), árabe (`ar`), coreano
  (`ko`), espanhol (`es-ES`), francês (`fr-FR`), holandês (`nl`), inglês (`en-US`), italiano
  (`it`), japonês (`ja`), polonês (`pl`), português do Brasil (`pt-BR`), russo (`ru`), sueco (`sv`)
  e turco (`tr`).
- O árabe ativa a direção da direita para a esquerda: `lang` e `dir` do `<html>` são ajustados na
  troca de idioma (`frontend/src/locales/index.ts`).
- O idioma escolhido é salvo na conta do usuário (`2bea654`), e não só no navegador.
- Os termos de uso e a política de privacidade estão em português e inglês
  (`frontend/src/modules/legal/conteudo.ts`).
- O teste `frontend/e2e/tema-idioma.spec.ts` cobre a troca de tema e de idioma, o `dir="rtl"` no
  árabe e a gravação de `idioma_preferido` no usuário.

## 3. Trabalhos futuros

- **Tempo de estudo por semana.** Hoje só existe o total acumulado
  (`progresso_usuario.tempo_total_estudo`). Para o gráfico seria preciso registrar cada sessão de
  estudo com a data. Os gráficos usam a nota média de pronúncia por semana no lugar.
- **Rotação do refresh token** a cada `POST /auth/refresh`, com detecção de reuso (um refresh
  token já trocado que aparece de novo indica roubo e derruba todas as sessões).
- **Revogar os tokens também em `POST /admin/users/role`**, como já acontece no
  `PUT /users/update/{id}`.
- **Integridade referencial:** `flashcard_tags.flashcard_id` e `nivelamentos.questao_atual_id` não
  têm chave estrangeira no banco.
- **Geração de imagens:** a tabela `imagens_geradas` e o CRUD existem, mas não há geração
  automática por IA.
- **Nivelamento:** revisar e calibrar as 30 questões com o orientador ou um especialista, e
  ampliar o banco de questões.
- **Testes E2E em outros navegadores:** hoje só Chromium.
- **Itens do roadmap em andamento:** o deploy (item 1) tem o Blueprint `render.yaml`, ainda não
  testado num deploy real, e a validação com usuários (item 6) tem o material e a exportação
  `beready:relatorio-estudo`, mas falta aplicar.
  [preencher: atualizar conforme o andamento.]

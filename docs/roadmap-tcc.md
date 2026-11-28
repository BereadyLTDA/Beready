# Roadmap para o TCC

O que falta para o BeReady fechar como TCC de uma plataforma de ensino de idiomas, em ordem de
prioridade. Cada item diz o objetivo, o que fazer, os arquivos envolvidos, quem faz e quando está
pronto.

Situação em 28/11/2026: itens 2, 3, 4, 5 e 8 implementados e na `main`, com testes e CI verde. Do
item 1, o Blueprint do Render está pronto; do 6, o material e a exportação; do 7, os diagramas e
os rascunhos. O que falta nos três depende de você.

| # | Item | Situação | Falta |
|---|---|---|---|
| 1 | Colocar no ar | Preparado | Criar o Blueprint no Render com as suas contas ([passo a passo](deploy-render.md)) |
| 2 | Áudio e pronúncia | Pronto | Nada |
| 3 | Gamificação simples | Pronto | Cron do scheduler no deploy (item 1) |
| 4 | Teste de nivelamento | Pronto | Revisar as 30 questões |
| 5 | Gráficos de progresso | Pronto | Nada (tempo de estudo por semana ficou de fora, ver abaixo) |
| 6 | Validação com usuários | Material pronto | Conduzir o estudo ([docs/validacao](validacao/README.md)) |
| 7 | Documentação do TCC | Diagramas prontos | Escrever o texto e preencher os `[preencher]` ([docs/tcc](tcc/README.md)) |
| 8 | LGPD | Pronto | Revisar os textos e definir o e-mail de contato |

### O que precisa da sua revisão

- **Questões do nivelamento:**
  - estão em `backend/database/migrations/2026_11_20_000000_cria_nivelamento.php`, 6 por nível;
  - para corrigir alguma depois de aplicada, crie uma migration nova que atualize a tabela
    `nivelamento_questoes`, em vez de editar essa.
- **Termos e política de privacidade:**
  - o texto está em `frontend/src/modules/legal/conteudo.ts`, em português e inglês;
  - revise com o orientador;
  - ao mudar o texto, atualize `VERSAO` nesse arquivo e `termos_versao` em
    `backend/config/beready.php`.
- **E-mail de contato de privacidade:**
  - defina `VITE_CONTATO_EMAIL` no `frontend/.env` e no build de produção;
  - sem ele, as páginas indicam o repositório do projeto.
- **Tempo de estudo por semana:** não entrou nos gráficos. Hoje o backend só guarda o total
  acumulado (`tempo_total_estudo`). Para ter o gráfico, seria preciso registrar cada sessão com a
  data; os gráficos usam a nota média de pronúncia por semana no lugar.

### Como ficaram os itens 1, 6 e 7

- **1. Colocar no ar:**
  - `render.yaml` cria a API (Docker), o cron do lembrete (21h UTC) e o frontend estático;
  - `docs/deploy-render.md` tem as variáveis, os passos nos consoles (Supabase, reCAPTCHA, OAuth,
    Stripe) e o teste de fumaça;
  - ainda não foi testado num deploy real.
- **6. Validação com usuários:**
  - `docs/validacao/`: TCLE, formulários (perfil e SUS), roteiro da tarefa guiada com folha de
    observação e modelo do capítulo de resultados;
  - `php artisan beready:relatorio-estudo` exporta as métricas em CSV, anonimizadas (P01, P02...);
  - `NIVELAMENTO_DIAS_PARA_REFAZER` encurta o prazo de 30 dias para o teste final do protocolo.
- **7. Documentação:**
  - `docs/tcc/`: 12 diagramas Mermaid (componentes, implantação, casos de uso, DER, 4 sequências)
    e o rascunho de decisões técnicas, qualidade e trabalhos futuros;
  - os números que não dá para medir no repositório (Lighthouse, desempenho) estão marcados com
    `[preencher]`.

### Como ficou cada item implementado

- **2. Áudio e pronúncia:**
  - botão ouvir (normal e devagar) em flashcards, traduções e frases;
  - exercício em `/pronuncia`, com a nota calculada no servidor pela maior sequência comum de
    palavras.
- **3. Gamificação:**
  - meta de cards por dia nas preferências, mostrada no dashboard;
  - 8 conquistas em `/conquistas`, conferidas no servidor;
  - lembrete por e-mail com `php artisan beready:lembretes`, todo dia às 18h.
- **4. Nivelamento:**
  - em `/nivelamento`, adaptativo (começa no B1, 12 questões), corrigido no servidor;
  - o resultado vira o `nivel_ingles` do aluno, e só dá para refazer depois de 30 dias.
- **5. Gráficos:**
  - em `/progresso`: revisões por dia, acerto e pronúncia por semana e distribuição dos cards;
  - SVG próprio, cada gráfico com uma tabela equivalente.
- **8. LGPD:**
  - páginas `/termos` e `/privacidade`;
  - aceite obrigatório no cadastro, com versão e data gravadas;
  - "Exportar meus dados" no perfil;
  - exclusão da conta confirmada por senha.

---

## 1. Colocar no ar

**Por quê:** a banca e o orientador precisam abrir um link. Também é o único jeito de fazer a
validação com usuários (item 6).

**Arquitetura sugerida (custo zero ou quase):**

| Parte | Onde | Observação |
|---|---|---|
| API (Laravel Octane + FrankenPHP) | Render ou Railway (Docker) | Imagem `Dockerfile.backend --target prod` |
| Frontend (Vue, estático) | Render Static Site, Netlify ou Vercel | Build com `npm run build` |
| Banco | Supabase (o atual) | Já tem os dados e as migrations aplicadas |
| Redis | Upstash (plano grátis) | Só se a API rodar em mais de uma instância |
| E-mail | Gmail SMTP (atual) ou Resend/Brevo | O Gmail limita cerca de 500 envios por dia |

**Passo a passo:**

1. **Antes de tudo, troque a senha do banco no Supabase.** A senha antiga está no histórico público
   do git.
2. **Domínio (opcional, recomendado):** algo como `beready.app`, ou os subdomínios grátis do
   Render/Netlify.
3. **API no Render:**
   - crie um Web Service apontando para `BereadyLTDA/Beready`, com o Dockerfile
     `Dockerfile.backend` e o target `prod`;
   - configure as variáveis de `backend/.env.example` com os valores de produção (lista abaixo);
   - o container roda as migrations ao subir (`docker/entrypoint.sh`).
4. **Frontend:**
   - build com `VITE_API_URL=https://<url-da-api>` e `VITE_RECAPTCHA_SITE_KEY`;
   - publique a pasta `frontend/dist`;
   - configure o fallback da SPA: todas as rotas caem no `index.html`, menos `/assets`, `.txt`,
     `.json`, `.xml` e `/.well-known` (como já está em `docker/nginx.conf`).
5. **reCAPTCHA:** adicione o domínio de produção no console do reCAPTCHA (google.com/recaptcha/admin).
6. **Login social:** nos consoles do Google, Facebook e LinkedIn, cadastre os callbacks
   `https://<api>/social-auth/callback/{google|facebook|linkedin}` e ajuste `*_REDIRECT_URI`.
7. **Stripe:**
   - crie o endpoint de webhook `https://<api>/payments/webhook` com os eventos
     `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`,
     `customer.subscription.updated` e `customer.subscription.deleted`;
   - copie o `whsec_` novo para `STRIPE_WEBHOOK_SECRET`.
   - Para a banca, mantenha o **modo de teste** (cartão `4242 4242 4242 4242`).
8. **GitHub:** troque o secret `VITE_API_URL` pela URL real e proteja a `main` (exigir CI verde).
9. **Teste de fumaça:**
   - cadastro, e-mail de confirmação e login;
   - login social;
   - assinatura no Stripe de teste com o webhook ativando o plano;
   - geração por IA.

**Variáveis de produção que mudam em relação ao local:**

- `APP_ENV=production` e `APP_DEBUG=false`.
- `APP_KEY` e `JWT_SECRET` novos, longos e aleatórios.
- `APP_URL`, com a URL da API, e `APP_BASE_URL`, com a URL do frontend terminando em `/`.
- `CORS_ALLOWED_ORIGINS`, só com a URL do frontend.
- `REFRESH_COOKIE_SECURE=true`. Com frontend e API em domínios diferentes, também
  `REFRESH_COOKIE_SAMESITE=None`.
- `TRUSTED_PROXIES=*`: Render e Railway ficam atrás de proxy.
- `REDIS_URL`, se houver mais de uma instância.
- `SENTRY_LARAVEL_DSN`, opcional, para ver os erros de produção.

**Pronto quando:** os dois links (frontend e API) estiverem no ar, o teste de fumaça passar e o
README tiver o link.

**Referência no repositório:** a skill `.claude/skills/deploy/SKILL.md` e a seção Docker do README.

---

## 2. Áudio e pronúncia

**Por quê:** uma plataforma de idiomas sem ouvir e falar fica incompleta, e a banca vai notar.
Dá para fazer sem custo com as APIs do próprio navegador.

**O que fazer:**

1. **Ouvir (texto para fala):**
   - um botão 🔊 nos flashcards (frente em inglês), nas traduções e nas frases semelhantes;
   - usa a `speechSynthesis` do navegador (`SpeechSynthesisUtterance` com `lang = 'en-US'`), sem
     backend e sem custo;
   - opção de velocidade normal ou lenta (0.7) e escolha de voz quando houver mais de uma em inglês;
   - nas preferências do usuário, "sotaque preferido": en-US ou en-GB.
2. **Falar (reconhecimento de voz):**
   - um exercício "Repita a frase": mostra a frase, a pessoa aperta 🎤 e fala;
   - usa a `SpeechRecognition` / `webkitSpeechRecognition`, que funciona no Chrome e no Edge;
   - compara o texto reconhecido com o esperado (normalizado, sem pontuação) por similaridade
     (distância de Levenshtein) e mostra uma nota de 0 a 100%, com as palavras que faltaram
     destacadas;
   - onde não houver suporte (Firefox, Safari), mostrar "seu navegador não suporta" e esconder o
     botão.
3. **Registrar no progresso:** cada exercício de fala conta como resposta (tipo `pronuncia`),
   registrada pelo servidor, não pelo cliente.

**Arquivos:**

- **Frontend novo:**
  - `src/shared/composables/useFala.ts` (texto para fala);
  - `src/shared/composables/useReconhecimentoVoz.ts`;
  - um componente `BotaoOuvir.vue` no kit de UI;
  - a view do exercício em `src/modules/pronuncia/`.
- **Frontend existente:** botões nos componentes de flashcard, tradução e frase.
- **Backend:**
  - rota `POST /pronuncia/tentativas` (salva frase, texto reconhecido e nota);
  - tabela `pronuncia_tentativas` com migration nova;
  - contrato OpenAPI e testes.
- **Traduções:** os textos novos nos 14 idiomas.

**Cuidados:**

- O reconhecimento de voz exige HTTPS (ou localhost) e permissão de microfone.
- No celular, o Chrome do Android funciona e o Safari do iOS é limitado.
- Acessibilidade: os botões precisam de rótulo e o resultado precisa ser anunciado
  (`aria-live`).

**Pronto quando:** dá para ouvir qualquer frase em inglês do app e fazer o exercício de repetir
com nota, e os testes cobrem a comparação de textos e o endpoint.

---

## 3. Gamificação simples

**Por quê:** retenção. A sequência de dias já existe (`progresso_usuario.sequencia_atual`); falta
dar objetivo e recompensa.

**O que fazer:**

1. **Meta diária:**
   - nas preferências, "cards por dia" (5, 10, 20 ou 30);
   - no dashboard, um anel de progresso "7/10 hoje".
2. **Conquistas (badges):**
   - lista fixa no backend, por exemplo:
     - primeiro card;
     - 50 e 200 cards revisados;
     - 7 e 30 dias seguidos;
     - primeiro quiz gabaritado;
     - 10 frases geradas com IA;
     - primeira pronúncia acima de 90%;
   - tabela `conquistas_usuario` (`usuario_id`, `codigo`, `conquistada_em`);
   - verificadas no servidor, sempre que o progresso muda (revisão, quiz finalizado, pronúncia);
   - aviso na tela quando ganha uma, e página "Conquistas" no perfil.
3. **Lembrete por e-mail:**
   - comando agendado (`php artisan beready:lembretes`, no scheduler do Laravel) que manda
     e-mail para quem tem cards vencidos e não estuda há 2 dias;
   - opção de desligar nas preferências (`notificacoes_ativas` já existe);
   - em produção, precisa de um cron chamando `php artisan schedule:run` a cada minuto (o Render
     tem Cron Job).

**Arquivos:**

- **Backend:**
  - `app/Services/ConquistaService.php`;
  - migration da tabela;
  - `app/Console/Commands/EnviarLembretes.php`;
  - `app/Mail/LembreteEstudo.php` e a view do e-mail;
  - rotas `GET /conquistas` e `GET /meta-diaria`;
  - contrato e testes.
- **Frontend:**
  - dashboard (anel da meta);
  - `src/modules/conquistas/`;
  - preferências (meta diária);
  - 14 idiomas.

**Pronto quando:** a meta aparece e atualiza no dashboard, as conquistas são dadas pelo servidor
(com testes para cada regra) e o lembrete sai para quem está inativo e não sai para quem desligou.

---

## 4. Teste de nivelamento

**Por quê:** hoje a pessoa escolhe o próprio nível (`nivel_ingles`). Um teste curto no primeiro
acesso dá um ponto de partida justo e é um bom tema para o capítulo de metodologia.

**O que fazer:**

1. **Banco de questões:**
   - cerca de 30 questões de múltipla escolha, classificadas por nível do CEFR (A1 a C1):
     gramática, vocabulário e compreensão de frases curtas;
   - ficam num seeder (`database/seeders/sql/nivelamento.sql`), não são geradas por IA na hora,
     para o teste ser estável;
   - você revisa as questões. O Claude pode gerar a primeira versão.
2. **Teste adaptativo simples:**
   - começa no B1, sobe um nível depois de 2 acertos seguidos e desce depois de 2 erros;
   - 12 a 15 questões no total;
   - corrigido no servidor (as respostas certas não vão para o navegador).
3. **Resultado:**
   - nível estimado (A1 a C1), mapeado para `iniciante`, `intermediario` ou `avancado`;
   - grava no usuário e numa tabela `nivelamentos` (histórico, para mostrar evolução);
   - dá para refazer depois de 30 dias.
4. **Fluxo:** depois de confirmar o e-mail e entrar pela primeira vez, o dashboard sugere "Descubra
   seu nível em 5 minutos" (com opção de pular).

**Arquivos:**

- **Backend:**
  - `app/Services/NivelamentoService.php`;
  - migrations `nivelamento_questoes` e `nivelamentos`;
  - rotas `POST /nivelamento/iniciar`, `POST /nivelamento/responder` e
    `GET /nivelamento/resultado`;
  - contrato e testes (inclusive o algoritmo adaptativo).
- **Frontend:** `src/modules/nivelamento/` e o card no dashboard, nos 14 idiomas.

**Pronto quando:** um usuário novo faz o teste, recebe o nível e vê o nível no perfil; os testes
cobrem subir e descer de nível e a correção no servidor.

---

## 5. Gráficos de progresso

**Por quê:** a banca gosta de ver evolução, e o aluno também. Hoje a página de progresso só mostra
números.

**O que fazer:**

1. **Acertos por semana:** um gráfico de linha da taxa de acerto nas últimas 8 a 12 semanas.
2. **Revisões por dia:** um gráfico de barras dos últimos 30 dias, ou um calendário de calor como o
   do GitHub.
3. **Distribuição dos cards:** novos, aprendendo e dominados, pelo intervalo da repetição espaçada.
4. **Tempo de estudo por semana.**

**Como:**

- **Backend:** `GET /progresso/historico?dias=90` agrega `respostas_usuario` por dia no servidor,
  com SQL agrupando por data, e devolve séries prontas.
- **Frontend:** uma biblioteca leve, como Chart.js com `vue-chartjs` (cerca de 60 KB) ou SVG feito
  à mão, carregada só na página de progresso para não pesar no Lighthouse das outras telas.
- As cores vêm dos tokens do design system, nos temas claro, escuro e daltônico.
- Cada gráfico tem uma tabela equivalente acessível (`<details>` com os dados), para leitores de
  tela.

**Pronto quando:** os 4 gráficos aparecem com dados reais nos três temas, o axe passa e o endpoint
tem teste de agregação.

---

## 6. Validação com usuários reais

**Por quê:** é o que mais pesa na nota. É o capítulo de resultados, que mostra que a plataforma
funciona para quem usa.

**O que fazer (você conduz):**

1. **Recrutar:** 5 a 10 pessoas (colegas, família), de níveis diferentes de inglês.
2. **Termo de consentimento (TCLE):** explicar o que é coletado e para quê. Se a faculdade exigir,
   passar pelo comitê de ética; pergunte ao orientador.
3. **Protocolo:**
   - Dia 0:
     - questionário de perfil (idade, nível, como estuda hoje);
     - teste de nivelamento (item 4);
     - tarefa guiada de 15 minutos: cadastro, criar 10 flashcards, fazer um quiz e gerar frases
       com IA.
   - 1 a 2 semanas de uso livre, com meta de 10 minutos por dia.
   - Final:
     - questionário **SUS** (System Usability Scale, 10 perguntas, nota de 0 a 100);
     - perguntas abertas (o que mais ajudou, o que atrapalhou);
     - refazer o nivelamento.
4. **Métricas do próprio sistema:**
   - dias ativos;
   - cards revisados;
   - taxa de acerto no início e no fim;
   - uso da IA;
   - pronúncia.

   O Claude pode criar um comando `php artisan beready:relatorio-estudo` que exporta isso em CSV,
   anonimizado.
5. **Análise:**
   - média do SUS (acima de 68 é considerado acima da média);
   - comparação entre antes e depois;
   - citações das respostas abertas.

**O que o Claude prepara quando você pedir:**

- o roteiro da tarefa guiada;
- o formulário (texto pronto para o Google Forms) com o SUS e as perguntas abertas;
- o modelo do TCLE;
- o comando de exportação;
- o rascunho do capítulo de resultados a partir dos dados.

**Pronto quando:** você tiver as respostas do SUS e os dados de uso de pelo menos 5 pessoas, e o
capítulo de resultados estiver escrito.

---

## 7. Documentação do TCC

**O que entra (você escreve; o Claude gera diagramas e rascunhos):**

1. **Arquitetura:**
   - diagrama de componentes (Vue SPA, API Laravel, PostgreSQL, Redis, Stripe, Anthropic,
     Cloudinary, OAuth);
   - diagrama de implantação (onde cada parte roda).

   Base: `docs/arquitetura.md`.
2. **Casos de uso:**
   - atores: visitante, aluno e administrador;
   - casos principais: cadastrar, confirmar e-mail, estudar flashcards, fazer quiz, gerar com IA,
     assinar plano, administrar planos e roles.
3. **Modelo do banco (DER):** gerado a partir de `backend/database/schema/esquema-inicial.sql` e
   das migrations.
4. **Diagramas de sequência:**
   - login com refresh token;
   - assinatura com o webhook do Stripe;
   - geração por IA;
   - verificação de e-mail.
5. **Decisões técnicas:**
   - a migração de CakePHP para Laravel: motivos (ecossistema, Octane, Socialite, comunidade),
     como a paridade foi garantida (180 testes e o contrato OpenAPI validando cada resposta) e os
     números;
   - JWT com refresh em cookie httpOnly;
   - a repetição espaçada (SM-2);
   - a revisão de segurança (revogação de tokens, verificação de e-mail, reCAPTCHA).
6. **Qualidade:**
   - o CI (PHPUnit, Larastan, Pint, Vitest, Playwright e o axe);
   - os números do Lighthouse;
   - a acessibilidade (WCAG 2.2 AA);
   - a internacionalização.
7. **Trabalhos futuros:** o que ficar de fora deste roadmap.

**Formato:** os diagramas em Mermaid ou PlantUML dentro de `docs/`, para versionar, e exportados
como imagem para o documento. O Claude gera todos quando você pedir.

---

## 8. LGPD: termos de uso, privacidade e exportação

**Por quê:** o sistema guarda e-mail, telefone, foto, histórico de estudo e pagamentos. A banca
costuma perguntar. Além disso, a LGPD vale para qualquer sistema que trate dados pessoais.

**O que fazer:**

1. **Páginas públicas:** `/termos` e `/privacidade`, com links no rodapé, no cadastro e no login.
   - Quais dados são coletados e para quê.
   - Base legal: execução do contrato e consentimento.
   - Com quem são compartilhados: Stripe (pagamento), Cloudinary (foto), Anthropic (o texto do
     prompt enviado para a IA), Google, Facebook e LinkedIn (login social) e o provedor de e-mail.
   - Por quanto tempo são guardados.
   - Os direitos do titular e o contato.
2. **Consentimento no cadastro:** uma caixa obrigatória "Li e aceito os termos e a política de
   privacidade", com a data e a versão do aceite gravadas no usuário (migration nova).
3. **Exportar meus dados:** um botão no perfil e `GET /users/me/exportar`, que devolve um JSON com
   perfil, flashcards, quizzes, prompts, progresso, preferências e histórico de assinaturas.
4. **Excluir minha conta:** já existe, e agora cancela o Stripe e apaga as tags. Falta deixar
   claro na tela o que é apagado e pedir a senha para confirmar.
5. **Cookies:** o app só usa o cookie técnico do refresh token (sem rastreamento), então não precisa
   de banner. Basta citar isso na política.

**Quem faz:** o Claude escreve as páginas, o consentimento, a exportação e os testes, e gera um
rascunho dos textos; você revisa os textos, de preferência com o orientador.

**Pronto quando:** as páginas estão no ar e linkadas, o cadastro exige o aceite (com a versão
gravada), a exportação traz todos os dados do usuário (com teste) e a exclusão está clara.

---

## Como pedir

Basta dizer "faz o item N do roadmap do TCC", ou "faz os itens 2 e 3". Cada item é entregue com:

- backend e frontend;
- contrato OpenAPI e tipos regenerados;
- testes (PHPUnit e Playwright, incluindo o axe);
- os 14 idiomas;
- commits na `main` com o CI verde.

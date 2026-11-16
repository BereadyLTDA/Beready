# BeReady - Plataforma de Aprendizado de Inglês

Aplicação web para aprendizado de inglês por contextualização visual, utilizando flashcards, quizzes, traduções e recursos interativos.

---

# Tecnologias Utilizadas

## Back-end

- PHP 8.2+ e CakePHP 5 (API REST, sem views)
- Injeção de dependências pelo container do Cake (autowiring)
- JWT (access token em memória + refresh token em cookie httpOnly)
- Login social com `league/oauth2-client` (Google, Facebook, LinkedIn)
- Stripe (assinatura recorrente), Cloudinary (fotos), Resend/SMTP (e-mail), Sentry (erros)
- Claude, via SDK oficial da Anthropic, para gerar traduções e frases
- Redis (opcional) para rate limit e contadores compartilhados entre instâncias
- PHPUnit (integração + contrato OpenAPI) e PHPStan nível 8

## Front-end

- Vue 3, TypeScript, Vite
- Pinia, Vue Router, vue-i18n (14 idiomas)
- Tailwind ligado a tokens de design (ver `frontend/docs/design-system.md`)
- Tipos da API gerados do contrato OpenAPI (`npm run gen:api`)
- Vitest (unitários) e Playwright (ponta a ponta + acessibilidade)

## Banco de Dados

- PostgreSQL 15 (local ou Supabase), com migrations do CakePHP

---

# Estrutura do Projeto

```txt
backend/
  config/routes.php        rotas (helpers $rota e $crud)
  openapi.yaml             contrato da API, validado pelos testes
  src/Application.php      middlewares e registro do container (interface => implementação)
  src/Controller/          recebem dependências pelo construtor
  src/Services/            regras de negócio
  src/Repositories/        acesso a dados (por trás das interfaces em src/Contracts/)
  src/Middleware/          CORS, rate limit, JWT, admin, limites do plano
  tests/                   integração (ApiTestCase), contrato e serviços
frontend/
  src/modules/<recurso>/   services, composables e views por recurso
  src/shared/              kit de UI, composables e utilitários
  src/core/types/          api.gen.ts (gerado) e tipos escritos à mão
  e2e/                     Playwright com API mockada
docker/                    configs das imagens de produção (Caddy, Nginx, php.ini)
```

Detalhes de arquitetura e de como adicionar uma rota nova: [`docs/arquitetura.md`](docs/arquitetura.md).

---

# Requisitos

Antes de começar, instale:

- PHP 8.2+
- Composer
- Node.js 20+
- NPM
- PostgreSQL ou Supabase
- Git

---

# Clonar o Projeto

```bash
git clone https://github.com/BraianGaspar/Beready_Projeto.git
cd Beready_Projeto
```

---

# Configuração do Banco de Dados

O projeto utiliza PostgreSQL via Supabase.

## Configurar conexão

Toda a configuração vem do `.env` (não há valores padrão no código). Copie os exemplos e preencha:

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

A conexão do banco fica em `DATABASE_URL` no `backend/.env`:

```txt
DATABASE_URL="postgres://usuario:senha@host:6543/postgres?encoding=utf8&timezone=UTC&cacheMetadata=true"
```

## Criar o banco do zero

A migration `Initial` cria todas as tabelas, e os seeds inserem roles, permissões e planos:

```bash
cd backend
php bin/cake.php migrations migrate
php bin/cake.php migrations seed
```

---

# Segurança do Banco

Recomendações importantes:

- Ativar Row Level Security (RLS) no Supabase
- Nunca expor `service_role_key`
- Utilizar variáveis `.env`
- Não subir credenciais para o GitHub
- Configurar políticas de acesso nas tabelas

---

# ⚙️ Configuração do Backend (CakePHP)

## Acessar pasta backend

```bash
cd backend
```

---

## Instalar dependências

```bash
composer install
```

---

## Rodar migrations

```bash
bin/cake migrations migrate
```

No Windows:

```bash
php bin/cake.php migrations migrate
```

---

## Rodar os testes

Os testes usam um Postgres separado, definido em `TEST_DATABASE_URL` no `backend/.env`. O banco de teste é apagado e recriado a cada execução, então nunca aponte para o banco real. Para subir um Postgres local de teste:

```bash
docker run -d --name beready_migtest -e POSTGRES_PASSWORD=test -e POSTGRES_DB=beready_test -p 55432:5432 postgres:15-alpine
```

```bash
cd backend
vendor/bin/phpunit
```

---

## Iniciar servidor backend

```bash
bin/cake server
```

Ou no Windows:

```bash
php bin/cake.php server
```

Backend disponível em:

```txt
http://localhost:8765
```

---

# Configuração do Frontend (Vue.js)

> Abra outro terminal para rodar o frontend simultaneamente.

## Acessar pasta frontend

```bash
cd frontend
```

---

## Instalar dependências

```bash
npm install
```

---

## Rodar ambiente de desenvolvimento

```bash
npm run dev
```

Frontend disponível em:

```txt
http://localhost:5173
```

---

# 🔗 Comunicação Frontend ↔ Backend

Verifique a URL da API no frontend.

Exemplo:

```env
VITE_API_URL=http://localhost:8765
```

---

# 🧠 Observações

- Backend e frontend rodam separadamente (ou juntos com `docker compose up`)
- O frontend consome a API REST do CakePHP; o formato das respostas está em `backend/openapi.yaml`
- O banco é PostgreSQL, local ou no Supabase (usado apenas como banco)

---

# Comandos Rápidos

## Backend

```bash
cd backend
composer install
php bin/cake.php migrations migrate
php bin/cake.php server
```

---

## Frontend

```bash
cd frontend
npm install
npm run dev
```

---

# Contrato da API (OpenAPI)

O contrato fica em `backend/openapi.yaml`. Os testes do backend validam cada resposta contra ele e
falham se uma rota não estiver descrita, então ele não tem como ficar desatualizado em silêncio.

Ao mudar o formato de uma resposta, atualize o `openapi.yaml` e regenere os tipos do frontend:

```bash
cd frontend
npm run gen:api   # gera src/core/types/api.gen.ts (não edite esse arquivo à mão)
```

O CI recusa o build se `api.gen.ts` estiver diferente do que o contrato gera.

---

# Geração por IA

Traduções e frases semelhantes de um prompt podem ser geradas com o Claude (botão "Gerar com IA").
Defina `ANTHROPIC_API_KEY` no `backend/.env`; sem a chave, só esses dois endpoints respondem 503.
Cada usuário pode gerar até `IA_LIMITE_DIARIO` vezes por dia (padrão 50).

---

# Docker

Desenvolvimento (API, frontend, Postgres e Redis):

```bash
docker compose up --build
```

Imagens de produção (API servida pelo FrankenPHP; frontend estático no Nginx):

```bash
docker build -f Dockerfile.backend  --target prod -t beready-api .
docker build -f Dockerfile.frontend --target prod --build-arg VITE_API_URL=https://api.exemplo.com -t beready-web .
```

Em produção a API lê a configuração das variáveis de ambiente do container (as mesmas do
`backend/.env.example`). Com mais de uma instância da API, defina `REDIS_URL`.

---

# Testes

```bash
cd backend && vendor/bin/phpunit           # integração + contrato da API
cd backend && vendor/bin/phpstan analyse   # análise estática (erros antigos em phpstan-baseline.neon)
cd frontend && npm run test:unit           # unitários (Vitest)
cd frontend && npm run test:e2e            # ponta a ponta (Playwright)
```

---

# Variáveis Sensíveis

Adicione ao `.gitignore`:

```gitignore
.env
.env.local
backend/config/app_local.php
```

---

# Autor

Projeto desenvolvido para fins educacionais e aprendizado de inglês com suporte visual e contextual.

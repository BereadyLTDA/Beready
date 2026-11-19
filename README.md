# BeReady

Aplicação web para aprender inglês por contextualização visual: o aluno cria prompts que viram
traduções, imagens e frases semelhantes, estuda com flashcards (repetição espaçada) e quizzes, e
acompanha a evolução em um painel de progresso.

## Tecnologias

### Back-end

- PHP 8.4 e Laravel 13 (API REST, sem views), servido em produção pelo Laravel Octane sobre o FrankenPHP
- Injeção de dependências pelo container do Laravel (autowiring)
- JWT (access token em memória + refresh token em cookie httpOnly)
- Login social com Laravel Socialite (Google, Facebook, LinkedIn)
- Stripe (assinatura recorrente), Cloudinary (fotos), SMTP (e-mail), Sentry (erros)
- Claude, via SDK oficial da Anthropic, para gerar traduções e frases
- Redis (opcional) para rate limit e contadores compartilhados entre instâncias
- PHPUnit (integração + contrato OpenAPI), Larastan nível 6 e Pint

### Front-end

- Vue 3, TypeScript, Vite
- Pinia, Vue Router, vue-i18n (14 idiomas)
- Tailwind ligado a tokens de design (ver `frontend/docs/design-system.md`)
- Tipos da API gerados do contrato OpenAPI (`npm run gen:api`)
- Vitest (unitários) e Playwright (ponta a ponta + acessibilidade)

### Banco de dados

- PostgreSQL 15 (local ou Supabase)

## Estrutura do projeto

```txt
backend/
  routes/api.php            rotas (públicas fora do grupo `jwt`)
  openapi.yaml              contrato da API, validado pelos testes
  bootstrap/app.php         middlewares e respostas de erro em JSON
  app/Http/Controllers/     recebem dependências pelo construtor
  app/Http/Middleware/      JWT, admin, limites do plano, rate limit
  app/Services/             regras de negócio
  app/Models/               models Eloquent
  config/beready.php        configuração da aplicação (lida do .env)
  database/                 esquema inicial (SQL) e dados iniciais (roles, permissões, planos)
  tests/                    integração (ApiTestCase), contrato e serviços
frontend/
  src/modules/<recurso>/    services, composables e views por recurso
  src/shared/               kit de UI, composables e utilitários
  src/core/types/           api.gen.ts (gerado) e tipos escritos à mão
  e2e/                      Playwright com API mockada
docker/                     php.ini, nginx e entrypoint das imagens de produção
```

Detalhes de arquitetura e de como adicionar uma rota: [`docs/arquitetura.md`](docs/arquitetura.md).

## Requisitos

- PHP 8.3+ (8.4 recomendado) com as extensões `pdo_pgsql`, `intl`, `mbstring`
- Composer
- Node.js 20.19+ ou 22.12+
- PostgreSQL 15 (local, Docker ou Supabase)
- Git

Ou só Docker (ver [Docker](#docker)).

## Como rodar

```bash
git clone https://github.com/BereadyLTDA/Beready.git
cd Beready
```

### Backend

```bash
cd backend
composer install
cp .env.example .env          # preencha DATABASE_URL, JWT_SECRET etc.
php artisan key:generate
php artisan migrate --seed    # esquema + roles, permissões e planos
php artisan serve --port=8765 # http://localhost:8765
```

Em um banco que já veio da versão em CakePHP, `php artisan migrate` não altera nada: a migration
inicial só se registra como aplicada.

### Frontend

```bash
cd frontend
npm install
cp .env.example .env          # VITE_API_URL=http://localhost:8765
npm run dev                   # http://localhost:5173
```

## Contrato da API (OpenAPI)

O contrato fica em `backend/openapi.yaml`. Os testes do backend validam cada resposta contra ele e
falham quando uma rota registrada não está descrita nele.

Ao mudar o formato de uma resposta, atualize o `openapi.yaml` e regenere os tipos do frontend:

```bash
cd frontend
npm run gen:api   # gera src/core/types/api.gen.ts (não edite esse arquivo à mão)
```

O CI recusa o build se `api.gen.ts` estiver diferente do que o contrato gera.

## Geração por IA

Traduções e frases semelhantes de um prompt podem ser geradas com o Claude (botão "Gerar com IA").
Defina `ANTHROPIC_API_KEY` no `backend/.env`; sem a chave, só esses dois endpoints respondem 503.
Cada usuário pode gerar até `IA_LIMITE_DIARIO` vezes por dia (padrão 50).

## Docker

Desenvolvimento (API, frontend, Postgres e Redis):

```bash
docker compose up --build
```

Imagens de produção (API com Laravel Octane + FrankenPHP; frontend estático no Nginx):

```bash
docker build -f Dockerfile.backend  --target prod -t beready-api .
docker build -f Dockerfile.frontend --target prod --build-arg VITE_API_URL=https://api.exemplo.com -t beready-web .
```

Em produção a API lê a configuração das variáveis de ambiente do container (as de
`backend/.env.example`, mais `APP_KEY`), roda as migrations ao subir e, com mais de uma instância,
precisa de `REDIS_URL`.

## Testes

Os testes do backend usam um Postgres descartável em `TEST_DATABASE_URL` (o esquema é apagado e
recriado a cada execução; nunca aponte para o banco real). Para subir um:

```bash
docker run -d --name beready_test_db -e POSTGRES_PASSWORD=test -e POSTGRES_DB=beready_test -p 55432:5432 postgres:15-alpine
```

```bash
cd backend && vendor/bin/phpunit            # integração + contrato da API
cd backend && vendor/bin/phpstan analyse    # Larastan (erros antigos em phpstan-baseline.neon)
cd backend && vendor/bin/pint --test        # estilo
cd frontend && npm run test:unit            # unitários (Vitest)
cd frontend && npm run test:e2e             # ponta a ponta (Playwright)
```

## Segurança

- Nunca versione `backend/.env` nem `frontend/.env` (já estão no `.gitignore`)
- No Supabase, ative Row Level Security nas tabelas e não exponha a `service_role_key`
- Em produção use `APP_DEBUG=false`; a `APP_KEY` e o `JWT_SECRET` devem ser longos e aleatórios

## Sobre

Projeto educacional desenvolvido em equipe.

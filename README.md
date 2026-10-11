# 📘 BeReady - Plataforma de Aprendizado de Inglês

Aplicação web full stack para aprendizado de inglês por contextualização visual. O aluno cria **prompts** que viram traduções, imagens e frases semelhantes, estuda com **flashcards** e **quizzes** e acompanha sua evolução em um **dashboard de progresso**.

---

# ✨ Funcionalidades

- **Autenticação** com JWT (Access + Refresh token), cadastro, login, logout e recuperação de senha por e-mail
- **Perfil do usuário** com edição de dados e upload de foto (Cloudinary)
- **Flashcards** com tags e modo de estudo
- **Quizzes** com criação, edição e modo de jogo, registrando as respostas do usuário
- **Prompts** com traduções, imagens geradas e frases semelhantes vinculadas
- **Progresso** do aluno em um dashboard
- **Preferências** do usuário
- **Painel administrativo** (RBAC) com gestão de papéis e estatísticas

---

# 🛠 Tecnologias Utilizadas

## Back-end

- CakePHP 5.2
- PHP 8.2+
- JWT (`firebase/php-jwt`)
- Cloudinary (upload de imagens)
- Sentry (monitoramento de erros)
- Symfony Mailer (envio de e-mails)

## Front-end

- Vue.js 3
- TypeScript
- Vite
- Pinia
- Vue Router
- Axios
- Tailwind CSS

## Banco de Dados

- PostgreSQL 15
- Supabase (opcional, como PostgreSQL gerenciado)

## Infraestrutura e Qualidade

- Docker e Docker Compose
- GitHub Actions (PHPUnit, PHPCS e PHPStan)
- ESLint, Oxlint e Prettier no frontend

---

# 📂 Estrutura do Projeto

```txt
backend/                → API REST em CakePHP
  src/Controller/       → Endpoints da API
  src/Services/         → Regras de negócio
  src/Repositories/     → Acesso a dados
  src/Contracts/        → Interfaces dos services e repositories
  src/Middleware/       → CORS, rate limit e autenticação JWT
  src/Exceptions/       → Exceções de domínio
  config/Migrations/    → Migrations do banco
frontend/               → Interface em Vue.js
  src/modules/          → Módulos por funcionalidade (auth, flashcards, quizes, prompts, ...)
  src/stores/           → Stores Pinia
  src/router/           → Rotas e guardas de autenticação
docker-compose.yml      → PostgreSQL + backend + frontend
```

## Arquitetura do backend

As requisições passam pelos middlewares de **CORS**, **rate limit** (100 requisições por minuto por cliente) e **autenticação JWT**. Depois disso, os controllers delegam para os **Services**, que concentram as regras de negócio e acessam o banco por meio dos **Repositories**. Services e Repositories são definidos por interfaces em `src/Contracts`.

---

# 🚀 Requisitos

Para rodar com Docker:

- Docker e Docker Compose
- Git

Para rodar manualmente:

- PHP 8.2+ (com extensão `pdo_pgsql`)
- Composer
- Node.js 20.19+ ou 22.12+
- NPM
- PostgreSQL ou Supabase
- Git

---

# 📥 Clonar o Projeto

```bash
git clone https://github.com/BraianGaspar/Beready_Projeto.git
cd Beready_Projeto
```

---

# 🔐 Variáveis de Ambiente

Copie os arquivos de exemplo e preencha com os seus valores:

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

## Backend (`backend/.env`)

| Variável | Descrição |
| --- | --- |
| `DATABASE_URL` | Conexão PostgreSQL. Ex.: `postgres://usuario:senha@host:5432/beready` |
| `SECURITY_SALT` | String aleatória usada pelo CakePHP |
| `JWT_SECRET` | Chave usada para assinar os tokens JWT |
| `APP_BASE_URL` | URL do frontend, usada como origem permitida no CORS e nos links de e-mail. Ex.: `http://localhost:5173` |
| `APP_ENV` | Ambiente (`development` ou `production`) |
| `DEBUG` | Ativa o modo debug do CakePHP |
| `EMAIL_TRANSPORT_DEFAULT_URL` | Transporte SMTP para envio de e-mails (recuperação de senha) |
| `EMAIL_FROM` | Remetente dos e-mails |
| `SENTRY_DSN` | DSN do Sentry para monitoramento de erros |
| `CLOUDINARY_CLOUD_NAME` | Nome da conta no Cloudinary |
| `CLOUDINARY_API_KEY` | Chave da API do Cloudinary |
| `CLOUDINARY_API_SECRET` | Segredo da API do Cloudinary |
| `CLOUDINARY_UPLOAD_FOLDER` | Pasta de upload. Ex.: `beready/profile` |

> `JWT_SECRET`, `EMAIL_TRANSPORT_DEFAULT_URL` e `EMAIL_FROM` não estão no `.env.example`. Adicione-as manualmente.

## Frontend (`frontend/.env`)

| Variável | Descrição |
| --- | --- |
| `VITE_API_URL` | URL da API. Ex.: `http://localhost:8765` |
| `VITE_APP_NAME` | Nome da aplicação |
| `VITE_APP_ENV` | Ambiente |

---

# 🐳 Rodando com Docker (recomendado)

Sobe o PostgreSQL, o backend e o frontend de uma vez:

```bash
docker compose up --build
```

Com os containers no ar, rode as migrations:

```bash
docker compose exec backend php bin/cake.php migrations migrate
```

| Serviço | URL |
| --- | --- |
| Frontend | http://localhost:5173 |
| Backend | http://localhost:8765 |
| PostgreSQL | localhost:5432 (usuário `beready_user`, banco `beready`) |

> As credenciais do `docker-compose.yml` são apenas para desenvolvimento local.

---

# ⚙️ Rodando Manualmente

## Backend (CakePHP)

```bash
cd backend
composer install
php bin/cake.php migrations migrate
php bin/cake.php server
```

Backend disponível em `http://localhost:8765`.

## Frontend (Vue.js)

> ⚠️ Abra outro terminal para rodar o frontend simultaneamente.

```bash
cd frontend
npm install
npm run dev
```

Frontend disponível em `http://localhost:5173`.

---

# 🧪 Testes e Qualidade

## Backend

```bash
cd backend
composer test        # PHPUnit
composer cs-check    # PHP CodeSniffer
composer cs-fix      # Corrige o estilo automaticamente
phpstan              # Análise estática (PHPStan instalado globalmente)
```

## Frontend

```bash
cd frontend
npm run type-check   # Checagem de tipos (vue-tsc)
npm run lint         # Oxlint + ESLint
npm run format       # Prettier
npm run build        # Build de produção
```

O GitHub Actions roda PHPUnit, PHPCS e PHPStan nos pull requests.

---

# 🔒 Segurança

- Nunca suba arquivos `.env` ou `backend/config/app_local.php` para o GitHub (já estão no `.gitignore`)
- Sempre defina `JWT_SECRET` e `SECURITY_SALT` fortes e diferentes por ambiente: o `config/app.php` tem um `JWT_SECRET` padrão que não deve ser usado fora do desenvolvimento
- Se usar Supabase, ative Row Level Security (RLS) e nunca exponha a `service_role_key`
- A API aplica rate limit de 100 requisições por minuto por cliente

---

# 👨‍💻 Autor

Projeto desenvolvido para fins educacionais e aprendizado de inglês com suporte visual e contextual.

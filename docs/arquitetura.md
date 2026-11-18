# Arquitetura

## Visão geral

```txt
Vue (SPA) ──HTTP/JSON──> Laravel (API REST) ──> PostgreSQL
                             │
                             ├─> Stripe (assinaturas + webhook)
                             ├─> Anthropic / Claude (traduções e frases)
                             ├─> Cloudinary (fotos de perfil)
                             ├─> Google / Facebook / LinkedIn (OAuth, via Socialite)
                             └─> Redis (opcional: rate limit e contadores)
```

O backend foi migrado de CakePHP 5 para Laravel 13 mantendo as mesmas URLs, o mesmo JSON e o
mesmo banco: o frontend não mudou. O código CakePHP continua no histórico do git.

## Caminho de uma requisição no backend

1. **CORS** (`config/cors.php`): só as origens de `CORS_ALLOWED_ORIGINS`, sempre com credenciais.
2. **LimitaRequisicoes** (grupo `api`): 300 req/min por IP; 10 req/min nas rotas de autenticação.
3. **jwt** (`AutenticaJwt`): valida o access token e define `user_id` no request. As rotas
   públicas ficam fora desse grupo em `routes/api.php`.
4. **admin** (`ApenasAdmin`): restringe `/admin/*` a quem tem role admin no banco.
5. **limite.plano:{recurso}** (`LimiteDoPlano`): barra criações acima do limite do plano.
6. Controller → Service → Model (Eloquent). O controller confere quem é o dono do recurso e monta
   a resposta; o service guarda a regra de negócio.

Formato das respostas: `{ success: true, message, data }` no sucesso e
`{ success: false, message, errors? }` no erro (`Controller::jsonSuccess/jsonError/errorResponse` e
o tratamento de exceções em `bootstrap/app.php`).

## Injeção de dependências

Controllers, middlewares e serviços declaram o que precisam no construtor; o container do Laravel
monta tudo por reflexão. Em `AppServiceProvider::$bindings` ficam só as decisões que a reflexão não
toma sozinha: qual classe implementa cada interface de `app/Contracts/`.

Nos testes, `$this->mockService(Interface::class, fn () => $fake)` troca uma implementação,
como fazem `Tests\FakeStripeGateway` e `Tests\FakeGeradorTexto`.

## Configuração

O código lê sempre `config('beready.*')` (arquivo `config/beready.php`), nunca `env()` direto, para
funcionar com `php artisan config:cache` em produção. Os nomes das variáveis de ambiente são os
mesmos da versão em CakePHP (ver `backend/.env.example`).

## Contrato da API

`backend/openapi.yaml` descreve todas as rotas. Dois mecanismos o mantêm em dia:

- `Tests\ApiTestCase` valida o status e o corpo de **cada resposta** dos testes de integração contra o contrato;
- `Tests\Feature\OpenApiContractTest` falha se uma rota registrada não estiver no contrato, ou
  vice-versa, e se `security: []` não bater com as rotas sem o middleware `jwt`. As rotas legadas
  (`/recurso/view|edit|delete/{id}`) ficam na lista `ALIASES`.

O frontend gera os tipos a partir do contrato (`npm run gen:api`).

## Geração por IA

`POST /prompts/{id}/traducoes/gerar` e `POST /prompts/{id}/frases/gerar`:

- `GeracaoService` valida a entrada, aplica o teto diário por usuário (`IA_LIMITE_DIARIO`) e grava o resultado;
- `ClaudeGeradorTexto` (implementação de `GeradorTextoInterface`) chama a API de Messages com saída
  estruturada (JSON Schema), esforço baixo e fallback no servidor em caso de recusa;
- falhas do provedor viram `GeracaoException`, com o status que o usuário recebe:
  503 (sem chave ou serviço ocupado), 502 (resposta inutilizável), 422 (recusa), 429 (limite diário).

## Adicionar uma rota

1. Registre-a em `routes/api.php`, dentro do grupo `jwt` se exigir login (públicas ficam fora dele).
2. Escreva a action no controller e, se precisar, um service (recebido pelo construtor).
3. Descreva a operação no `openapi.yaml`; o `OpenApiContractTest` avisa se você esquecer.
4. Escreva o teste de integração estendendo `Tests\ApiTestCase`; a resposta é validada contra o contrato.
5. No frontend, rode `npm run gen:api` e use os tipos gerados.

## Pontos de atenção em produção

- **Octane**: o processo atende várias requisições. Não guarde estado de request em propriedades de
  controllers, services ou singletons (use o próprio `Request`).
- **Redis**: com mais de uma instância da API, `REDIS_URL` é obrigatório (rate limit e códigos do
  login social precisam ser compartilhados).
- **Banco vindo do CakePHP**: a migration `0001_01_01_000000_esquema_inicial` não recria nada se as
  tabelas já existirem.

## Variáveis

| Variável | Para quê |
|---|---|
| `APP_KEY` | Chave do Laravel (`php artisan key:generate`) |
| `DATABASE_URL` | Postgres da aplicação |
| `TEST_DATABASE_URL` | Postgres descartável dos testes (o esquema é recriado a cada execução) |
| `JWT_SECRET` | Assinatura dos tokens de acesso e de refresh |
| `REDIS_URL` | Contadores compartilhados; obrigatório com mais de uma instância da API |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `IA_LIMITE_DIARIO` | Geração por IA |

A lista completa está em `backend/.env.example`.

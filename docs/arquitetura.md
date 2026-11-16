# Arquitetura

## Visão geral

```txt
Vue (SPA) ──HTTP/JSON──> CakePHP (API REST) ──> PostgreSQL
                             │
                             ├─> Stripe (assinaturas + webhook)
                             ├─> Anthropic / Claude (traduções e frases)
                             ├─> Cloudinary (fotos de perfil)
                             ├─> Google / Facebook / LinkedIn (OAuth)
                             └─> Redis (opcional: rate limit e contadores)
```

## Caminho de uma requisição no backend

Middlewares, na ordem (`src/Application.php`):

1. **CORS**: primeiro, para que até as respostas de erro levem os headers.
2. **ErrorHandler**: converte exceções em JSON e envia os 5xx ao Sentry.
3. **BodyParser**
4. **RateLimit**: 300 req/min por IP; 10 req/min nas rotas de autenticação.
5. **JwtAuth**: valida o access token e define `user_id`; as rotas públicas estão nas constantes `PUBLIC_*`.
6. **Admin**: restringe `/admin/*` a quem tem role admin no banco.
7. **PlanoLimite**: barra criações acima do limite do plano.
8. **Routing**, que leva ao controller.

Controller → Service → Repository → Table do ORM. O controller confere quem é o dono do recurso e
monta a resposta; o service guarda a regra de negócio.

Formato das respostas: `{ success: true, message, data }` no sucesso e
`{ success: false, message, errors? }` no erro (`AppController` e `ResourceErrorTrait`).

## Injeção de dependências

Controllers, middlewares e serviços declaram o que precisam no construtor; o container do Cake
monta tudo por reflexão. Em `Application::services()` ficam apenas as decisões que a reflexão não
toma sozinha: qual classe implementa cada interface de `src/Contracts/`.

Nos testes, `$this->mockService(Interface::class, fn () => $fake)` troca uma implementação,
como fazem `FakeStripeGateway` e `FakeGeradorTexto`.

## Contrato da API

`backend/openapi.yaml` descreve todas as rotas. Dois mecanismos o mantêm em dia:

- `ApiTestCase` valida o status e o corpo de **cada resposta** dos testes de integração contra o contrato;
- `OpenApiContractTest` falha se uma rota registrada não estiver no contrato, ou vice-versa.
  As rotas legadas (`/recurso/view|edit|delete/{id}`) ficam na lista `ALIASES`.

O frontend gera os tipos a partir do contrato (`npm run gen:api`).

## Geração por IA

`POST /prompts/{id}/traducoes/gerar` e `POST /prompts/{id}/frases/gerar`:

- `GeracaoService` valida a entrada, aplica o teto diário por usuário (`IA_LIMITE_DIARIO`) e grava o resultado;
- `ClaudeGeradorTexto` (implementação de `GeradorTextoInterface`) chama a API de Messages com saída
  estruturada (JSON Schema), esforço baixo e fallback no servidor em caso de recusa;
- falhas do provedor viram `GeracaoException`, com o status que o usuário recebe:
  503 (sem chave ou serviço ocupado), 502 (resposta inutilizável), 422 (recusa), 429 (limite diário).

## Adicionar uma rota

1. Registre-a em `config/routes.php` com `$rota('POST', '/caminho/{id}', 'Controller::action', $id, ['id'])`.
2. Se ela for pública, inclua-a nas constantes `PUBLIC_*` do `JwtAuthMiddleware`.
3. Escreva a action e, se precisar, um service (recebido pelo construtor do controller).
4. Descreva a operação no `openapi.yaml`; o `OpenApiContractTest` avisa se você esquecer.
5. Escreva o teste de integração; a resposta é validada contra o contrato automaticamente.
6. No frontend, rode `npm run gen:api` e use os tipos gerados.

## Ambientes

| Variável | Para quê |
|---|---|
| `DATABASE_URL` | Postgres da aplicação |
| `TEST_DATABASE_URL` | Postgres descartável dos testes (o schema é recriado a cada execução) |
| `REDIS_URL` | Contadores compartilhados; obrigatório com mais de uma instância da API |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `IA_LIMITE_DIARIO` | Geração por IA |

A lista completa está em `backend/.env.example`.

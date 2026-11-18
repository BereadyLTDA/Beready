---
name: nova-rota-api
description: Passo a passo para criar ou alterar um endpoint da API do BeReady (Laravel) mantendo o contrato OpenAPI, os testes e os tipos do frontend em sincronia - rota em routes/api.php, controller, service, operação no backend/openapi.yaml, teste de integração e `npm run gen:api` no frontend. Use sempre que for adicionar, renomear ou mudar o formato de resposta de qualquer rota da API, criar um recurso novo, ou quando o OpenApiContractTest reclamar de rota sem operação, mesmo que o pedido só diga "faz o backend devolver X" ou "cria a tela de Y" e isso exija um endpoint.
---

# Criar ou alterar uma rota da API

O backend tem uma regra que segura tudo: **cada resposta dos testes é validada contra
`backend/openapi.yaml`**, e o `OpenApiContractTest` falha se uma rota não estiver no contrato (ou
vice-versa). O frontend gera seus tipos desse mesmo arquivo. Por isso os passos abaixo andam juntos;
pular um quebra o CI. A referência de arquitetura completa está em `docs/arquitetura.md`.

## 1. Rota (`backend/routes/api.php`)

- Precisa de login? Coloque dentro do grupo `Route::middleware('jwt')`. Pública? Fora dele.
  O contrato marca públicas com `security: []` e o teste confere que as duas coisas batem.
- Só admin: dentro do grupo `admin` (`/admin/*`).
- Criação sujeita ao limite do plano: `->middleware('limite.plano:<recurso>')`.
- Parâmetros numéricos com `->where('id', $id)`. Use os mesmos nomes de parâmetro no contrato
  (`{id}`, `{usuarioId}`...), porque a comparação é por texto do template.

## 2. Controller e service

- Controller em `app/Http/Controllers/`, estendendo `Controller`. Receba dependências pelo
  construtor (o container resolve).
- Respostas sempre pelos helpers, que mantêm o formato que o frontend espera:
  `jsonSuccess($data, 'Mensagem', 201)`, `jsonError('Mensagem', 404)`, e no `catch`
  `errorResponse($e, 'Erro ao ...')`.
- Dono do recurso: `currentUserId()` e `canAccessUser($donoId)` (dono ou admin). Recurso de outro
  usuário responde **404** (não 403), para não revelar que existe.
- Regra de negócio no service (`app/Services/`). Erros de validação de regra:
  `throw new ValidationException('Mensagem', ['campo' => ['regra' => 'mensagem']])` → vira 422.
- Configuração sempre por `config('beready.*')`, nunca `env()` (quebra com `config:cache` em produção).
- Nada de estado de requisição em propriedades do controller/service: em produção o Octane mantém o
  processo vivo entre requisições.

## 3. Contrato (`backend/openapi.yaml`)

Adicione a operação em `paths`, com:

- `operationId` único em camelCase e uma `tag`;
- `security: []` se for pública;
- `requestBody` se houver corpo;
- cada status que a rota devolve (`200`/`201` com o schema do `data`, e `default` com `Erro`).

Reaproveite os schemas de `components/schemas` (Flashcard, Quiz, Tag...) em vez de duplicar.
Rotas gêmeas (mesma action em outro path, como `/recurso/view/{id}`) não entram no contrato: vão em
`OpenApiContractTest::ALIASES`.

## 4. Teste de integração (`backend/tests/Feature/`)

Estenda `Tests\ApiTestCase`. Os helpers já fazem o grosso:

```php
$user = $this->createUser();
$this->actingAs($user);
$this->postJson('/recurso', ['campo' => 'valor']);
$this->assertResponseCode(201);
$this->assertSame('valor', $this->responseJson()['data']['campo']);
```

Cubra pelo menos: caminho feliz, validação (422), recurso de outro usuário (404) e sem token (401)
se a rota for protegida. A resposta é validada contra o contrato automaticamente em cada chamada.
Serviços externos (Stripe, IA) são trocados por fakes com `$this->mockService(...)`.

## 5. Frontend

```bash
cd frontend
npm run gen:api   # atualiza src/core/types/api.gen.ts a partir do contrato
```

Versione o `api.gen.ts` junto (o CI compara). Use os tipos gerados no service do módulo
(`src/modules/<recurso>/services/`), por exemplo
`components['schemas']['Flashcard']`.

## 6. Conferir

Use a skill `rodar-testes`: PHPUnit (inclui o `OpenApiContractTest`), Larastan, Pint e, no frontend,
`npm run build`.

## Mudando o formato de uma resposta existente

Mesmo caminho, começando pelo contrato: altere o schema no `openapi.yaml`, ajuste o código até os
testes passarem e rode `npm run gen:api`. Se o frontend usa o campo que mudou, o `vue-tsc` do build
aponta onde ajustar.

---
name: rodar-testes
description: Como rodar os testes do BeReady - subir o Postgres descartável, rodar PHPUnit (backend Laravel, inclusive a validação do contrato OpenAPI), Larastan e Pint, e Vitest e Playwright (frontend Vue). Use sempre que for rodar, depurar ou interpretar testes deste repositório, confirmar que uma mudança não quebrou nada, investigar falha de CI, ou quando aparecer "Resposta fora do contrato OpenAPI", mesmo que o pedido não diga "teste" (ex.: "vê se está tudo verde", "o CI falhou").
---

# Rodar os testes do BeReady

O projeto tem quatro camadas de verificação. Rode só as que a mudança afeta, mas antes de
dizer que algo está pronto rode a camada inteira do lado que você mexeu (backend ou frontend).

| Mudança em | Rode |
|---|---|
| `backend/` | PHPUnit + Larastan + Pint |
| `backend/openapi.yaml` | PHPUnit (contrato) + `npm run gen:api` no frontend |
| `frontend/src/` | Vitest + `npm run build` (type-check) + lint |
| telas/fluxos do frontend | Playwright |

## 1. Postgres descartável (backend)

Os testes do backend usam `RefreshDatabase`: **apagam e recriam o esquema a cada execução**.
Por isso `tests/bootstrap.php` se recusa a rodar sem `TEST_DATABASE_URL`, e essa URL nunca pode
apontar para o banco de desenvolvimento ou de produção.

```bash
docker run -d --name beready_test_db -e POSTGRES_PASSWORD=test -e POSTGRES_DB=beready_test \
  -p 55432:5432 postgres:15-alpine
```

Defina em `backend/.env` (ou no ambiente):

```
TEST_DATABASE_URL="postgres://postgres:test@127.0.0.1:55432/beready_test"
```

Se o container já existe e só está parado: `docker start beready_test_db`.

## 2. Backend (Laravel)

```bash
cd backend
vendor/bin/phpunit                          # tudo (~180 testes)
vendor/bin/phpunit --filter AssinaturasTest # um arquivo ou método
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test                      # só confere; `vendor/bin/pint` corrige
```

Os valores de ambiente dos testes (JWT, Stripe, CORS...) já estão em `backend/phpunit.xml`;
não é preciso preencher o `.env` além do `TEST_DATABASE_URL`.

### Quando falha com "Resposta fora do contrato OpenAPI"

Toda resposta dos testes de integração (`Tests\ApiTestCase`) é validada contra
`backend/openapi.yaml`. A mensagem mostra a operação, o status, o campo e o corpo. Decida qual
dos dois está errado:

- a API mudou de propósito → atualize o `openapi.yaml` e rode `npm run gen:api` no frontend;
- a API mudou sem querer (campo sumiu, tipo trocou, status novo) → corrija o código.

Não desligue `$validarContrato` para fazer o teste passar: ele existe justamente para o frontend
não ser surpreendido.

### Falhas comuns

- `connection refused` na porta 55432 → o container do Postgres não está rodando.
- `Defina TEST_DATABASE_URL` → falta a variável (passo 1).
- `Lazy loading ... is disabled` → fora de produção o Laravel proíbe consulta extra escondida (N+1);
  use `with()`/`load()` no código em vez de desligar a proteção.
- Larastan com erro novo → corrija; a `phpstan-baseline.neon` guarda só os erros antigos e não deve
  ganhar entradas novas.

## 3. Frontend (Vue)

```bash
cd frontend
npm run test:unit      # Vitest (src/**/*.spec.ts)
npm run build          # vue-tsc (type-check) + build
npm run lint
npm run test:e2e       # Playwright; a API é mockada em e2e/support/api.ts, não precisa do backend
npm run test:e2e:ui    # Playwright em modo interativo, para depurar
```

Na primeira vez, o Playwright precisa do navegador: `npx playwright install chromium`.

Se o CI acusar `api.gen.ts` desatualizado: rode `npm run gen:api` e versione o arquivo gerado.

## 4. Antes de dizer que terminou

Informe o resumo de cada camada que rodou (ex.: `OK (180 tests, 1993 assertions)`). Se algo falhou
ou não pôde ser rodado (ex.: sem Docker para o Postgres), diga isso explicitamente em vez de
presumir que passaria.

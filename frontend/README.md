# BeReady — frontend

SPA em Vue 3 + TypeScript + Vite que consome a API do backend (`../backend`).

## Rodar

```sh
npm install
cp .env.example .env   # VITE_API_URL aponta para a API (padrão http://localhost:8765)
npm run dev            # http://localhost:5173
```

## Scripts

| Comando | O que faz |
|---|---|
| `npm run dev` | Servidor de desenvolvimento com HMR |
| `npm run build` | Type-check (`vue-tsc`) e build de produção em `dist/` |
| `npm run lint` | oxlint + ESLint (com `--fix`) |
| `npm run format` | Prettier em `src/` |
| `npm run test:unit` | Testes unitários (Vitest, arquivos `src/**/*.spec.ts`) |
| `npm run test:e2e` | Testes ponta a ponta (Playwright, pasta `e2e/`, API mockada) |
| `npm run gen:api` | Regera `src/core/types/api.gen.ts` a partir de `../backend/openapi.yaml` |

## Organização

```txt
src/
  core/services/api.ts     axios único: injeta o access token e renova a sessão no 401
  core/types/              api.gen.ts (gerado, não editar) e index.ts (tipos escritos à mão)
  modules/<recurso>/       services (chamadas HTTP), composables (estado + alertas) e views
  shared/components/ui/    kit de UI (Base*, PageHeader, EmptyState...)
  shared/composables/      useForm, useAlert, useTheme, usePlan...
  stores/                  Pinia: auth e permissões
  locales/                 14 idiomas; pt-BR é a referência
```

Cada view usa um composable do próprio módulo (`useFlashcards`, `useTraducoes`...), que chama o
service e já exibe os alertas de sucesso e erro.

## Tipos da API

O contrato da API fica em `../backend/openapi.yaml`, e os testes do backend garantem que ele bate
com as respostas reais. Depois de alterar o contrato, rode `npm run gen:api` e versione o
`api.gen.ts`; o CI falha se o arquivo estiver desatualizado.

Os tipos de `index.ts` ainda são mantidos à mão. Ao mexer em um deles, prefira derivar do gerado:

```ts
import type { components } from './api.gen'
export type Traducao = components['schemas']['Traducao']
```

## Outras referências

- Design system e tokens: [`docs/design-system.md`](docs/design-system.md)
- Testes E2E: [`e2e/README.md`](e2e/README.md)

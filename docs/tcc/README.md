# Material para a documentação do TCC

Diagramas em Mermaid e rascunhos de texto para o item 7 de `docs/roadmap-tcc.md`. Os diagramas
ficam versionados junto com o código e o GitHub os renderiza direto na visualização dos arquivos
`.md`.

## Arquivos

| Arquivo | Conteúdo |
|---|---|
| [arquitetura.md](arquitetura.md) | Diagrama de componentes (SPA, API, banco, Redis e serviços externos) e diagrama de implantação (Render ou Railway, site estático, Supabase, Upstash, cron do scheduler). |
| [casos-de-uso.md](casos-de-uso.md) | Atores visitante, aluno e administrador; 3 diagramas de casos de uso e a tabela com a descrição, as rotas da API e as telas de cada caso (UC01 a UC27). |
| [modelo-de-dados.md](modelo-de-dados.md) | DER das 26 tabelas de domínio em 3 diagramas: usuários, acesso e planos; estudo; acompanhamento, gamificação e nivelamento. |
| [sequencias.md](sequencias.md) | Diagramas de sequência: login com refresh token, assinatura com o webhook do Stripe, geração por IA e verificação de e-mail. |
| [decisoes-e-qualidade.md](decisoes-e-qualidade.md) | Rascunho das decisões técnicas (migração para Laravel, JWT, SM-2, segurança), da qualidade (CI, Lighthouse, acessibilidade, i18n) e dos trabalhos futuros. |

Os trechos marcados com `[preencher: ...]` dependem de um dado que não está no repositório (por
exemplo, os números do Lighthouse). Procure por eles antes da entrega:

```sh
grep -rn "\[preencher" docs/tcc
```

## Exportar os diagramas como imagem

### Pela linha de comando (mermaid-cli)

Precisa do Node.js. O `mmdc` lê um `.md`, gera uma imagem para cada bloco ```` ```mermaid ```` e
numera os arquivos de saída (`saida-1.png`, `saida-2.png`...):

```sh
npx -p @mermaid-js/mermaid-cli mmdc -i docs/tcc/sequencias.md -o sequencias.png
```

Opções úteis:

- `-o saida.svg` gera SVG, que não perde qualidade ao ampliar no documento;
- `-b white` define o fundo branco (o padrão do PNG é transparente);
- `-s 2` dobra a resolução do PNG;
- `-w 1600` define a largura da página de renderização.

Exemplo para todos os arquivos, em PNG com fundo branco:

```sh
mkdir -p docs/tcc/img
for f in arquitetura casos-de-uso modelo-de-dados sequencias; do
  npx -p @mermaid-js/mermaid-cli mmdc -i docs/tcc/$f.md -o docs/tcc/img/$f.png -b white -s 2
done
```

Na primeira execução o `npx` baixa o pacote e um Chromium para renderizar.

### Pelo navegador (mermaid.live)

1. Abra https://mermaid.live.
2. Cole o conteúdo de um bloco, sem as linhas ```` ```mermaid ```` e ```` ``` ````.
3. Use "Actions" para baixar em PNG ou SVG.

### Pelo GitHub

Os arquivos abrem renderizados no GitHub. Cada diagrama tem um menu para ampliar e copiar, mas
para o documento do TCC prefira o SVG ou o PNG gerado pelo `mmdc`, que tem resolução controlada.

## Ao mudar o sistema

Os diagramas foram feitos a partir do código em 28/11/2026. Se as rotas, as tabelas ou os fluxos
mudarem, atualize o diagrama correspondente:

- rotas novas: `backend/routes/api.php` e `frontend/src/router/index.ts` → `casos-de-uso.md`;
- migrations novas em `backend/database/migrations/` → `modelo-de-dados.md`;
- mudanças em autenticação, Stripe ou IA → `sequencias.md`.

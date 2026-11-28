# Validação com usuários

Material do item 6 do [roadmap do TCC](../roadmap-tcc.md). Você conduz o estudo; os arquivos
abaixo são o que você entrega aos participantes e o que usa para analisar os dados.

| Arquivo | Para quê |
|---|---|
| [tcle.md](tcle.md) | Termo de consentimento. Cada participante assina antes de começar. |
| [formularios.md](formularios.md) | Textos prontos para dois Google Forms: perfil (dia 0) e avaliação final (SUS + perguntas abertas). |
| [roteiro-tarefa-guiada.md](roteiro-tarefa-guiada.md) | A tarefa de 15 minutos do dia 0, com o que observar e anotar. |
| [capitulo-resultados.md](capitulo-resultados.md) | Modelo do capítulo de resultados, com as tabelas a preencher. |

## Antes de começar

1. **Pergunte ao orientador se precisa do comitê de ética (CEP).** Pesquisa de opinião sem
   identificação costuma ser dispensada (Resolução CNS 510/2016, art. 1º), mas a decisão é da
   faculdade. Se precisar, o TCLE daqui serve de base para a Plataforma Brasil.
2. **Coloque o app no ar** (item 1 do roadmap). O reconhecimento de voz exige HTTPS.
3. **Encurte o prazo para refazer o nivelamento.** O padrão é 30 dias, e o protocolo refaz o teste
   ao fim de 1 a 2 semanas. Na API de produção, durante o estudo:

   ```
   NIVELAMENTO_DIAS_PARA_REFAZER=7
   ```

   Volte para o padrão (apague a variável) depois do estudo.
4. **Tire os limites do plano gratuito durante o estudo.** Os planos limitam quantos flashcards,
   quizzes e prompts cada um cria (`planos.limites`). Para o limite não virar resultado do estudo,
   no painel de administrador (`/admin`, aba de planos) deixe os limites do plano gratuito em `-1`
   (ilimitado) e volte aos valores antigos depois. Anote os valores originais antes de mudar. O
   teto diário de gerações por IA (`IA_LIMITE_DIARIO`, padrão 50) é separado e costuma bastar.
5. **Crie os dois formulários** com o texto de [formularios.md](formularios.md). Use a mesma
   pergunta de e-mail nos dois, para ligar as respostas ao relatório de uso.

## Cronograma

| Quando | O quê | Duração |
|---|---|---|
| Dia 0 | TCLE, formulário de perfil, nivelamento e tarefa guiada (com você acompanhando) | 30 a 40 min |
| Dias 1 a 14 | Uso livre, meta de 10 minutos por dia | 10 min/dia |
| Último dia | Refazer o nivelamento e responder o formulário final | 15 min |

Para 7 dias de uso livre em vez de 14, mantenha o resto igual e diga isso no capítulo.

## Exportar as métricas de uso

No fim do estudo, gere o CSV anonimizado. Só entram os e-mails que você listar, então liste só
quem assinou o TCLE.

1. Crie um arquivo `participantes.txt` fora do repositório, com um e-mail por linha. A ordem
   define o código: a primeira linha vira P01, a segunda P02, e assim por diante. Guarde esse
   arquivo com você. Ele é a única ligação entre o código e a pessoa.
2. Rode, com o `.env` apontando para o banco de produção:

   ```bash
   cd backend
   php artisan beready:relatorio-estudo --arquivo=../../participantes.txt \
     --de=2026-12-01 --ate=2026-12-14 --separador=";" --saida=../../relatorio.csv
   ```

   - `--de` e `--ate` são o primeiro e o último dia do estudo.
   - `--separador=";"` abre direto no Excel em português. Sem ele, sai com vírgula.
   - E-mail sem cadastro gera um aviso com o número da linha e fica de fora.

O CSV não traz nome, e-mail, telefone nem o texto dos cards. Colunas:

| Coluna | O que é |
|---|---|
| `participante` | P01, P02... |
| `nivel_ingles_atual` | Nível no perfil hoje (iniciante, intermediario, avancado) |
| `nivelamento_inicial` | Resultado (A1 a C1) do primeiro teste feito até o fim do estudo |
| `nivelamento_final` | Resultado do último teste feito no período, se for diferente do inicial |
| `dias_ativos` | Dias com qualquer atividade: revisão, quiz, pronúncia, card ou prompt criado |
| `flashcards_criados` | Cards criados no período |
| `revisoes_flashcard` e `acerto_flashcard_pct` | Revisões de flashcard e % de acerto |
| `respostas_quiz` e `acerto_quiz_pct` | Questões de quiz respondidas e % de acerto |
| `acerto_primeira_metade_pct` e `acerto_segunda_metade_pct` | % de acerto (flashcard + quiz) na 1ª e na 2ª metade do período |
| `prompts_criados` e `frases_semelhantes` | Prompts criados e frases semelhantes ligadas a eles (uso da IA) |
| `pronuncia_tentativas` e `pronuncia_nota_media` | Exercícios de pronúncia e nota média (0 a 100) |
| `maior_sequencia` | Maior sequência de dias seguidos de estudo |
| `conquistas_no_periodo` | Conquistas ganhas no período |

Célula vazia quer dizer "sem dado" (por exemplo, nenhum quiz respondido), não zero.

## Calcular o SUS

Cada uma das 10 afirmações tem nota de 1 (discordo totalmente) a 5 (concordo totalmente).

- Itens ímpares (1, 3, 5, 7, 9): nota − 1.
- Itens pares (2, 4, 6, 8, 10): 5 − nota.
- Some os 10 valores e multiplique por 2,5. O resultado vai de 0 a 100.

No Google Sheets, com as respostas de um participante em B2:K2 (item 1 em B, item 10 em K):

```
=2,5*((B2-1)+(5-C2)+(D2-1)+(5-E2)+(F2-1)+(5-G2)+(H2-1)+(5-I2)+(J2-1)+(5-K2))
```

Para ler o número: 68 é a média de referência (Sauro, 2011). Acima de 68, acima da média; acima de
80,3, entre os 10% melhores; abaixo de 51, problema sério de usabilidade. Com 5 a 10 pessoas,
informe a média, o desvio padrão, o mínimo e o máximo, e não tire conclusão estatística forte.

## Checklist

- [ ] Orientador consultado sobre o CEP
- [ ] App no ar, com `NIVELAMENTO_DIAS_PARA_REFAZER` ajustado
- [ ] Limites do plano gratuito liberados (valores originais anotados)
- [ ] Os dois formulários criados e testados
- [ ] 5 a 10 participantes recrutados, de níveis diferentes
- [ ] TCLE assinado por todos
- [ ] Dia 0 feito com cada um (anotações da tarefa guiada guardadas)
- [ ] Nivelamento final e formulário final respondidos
- [ ] `relatorio.csv` exportado
- [ ] SUS calculado e capítulo escrito
- [ ] `NIVELAMENTO_DIAS_PARA_REFAZER` removido da produção e limites do plano gratuito restaurados

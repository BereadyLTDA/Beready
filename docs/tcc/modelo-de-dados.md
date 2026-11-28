# Modelo de dados (DER)

Base:

- `backend/database/schema/esquema-inicial.sql`, carregado pela migration
  `0001_01_01_000000_esquema_inicial.php` (esquema herdado da versão em CakePHP);
- `2026_11_19_000000_adiciona_verificacao_de_email.php`;
- `2026_11_19_000001_cria_pronuncia_tentativas.php`;
- `2026_11_19_000002_cria_gamificacao.php`;
- `2026_11_20_000000_cria_nivelamento.php`;
- `2026_11_20_000001_registra_aceite_dos_termos.php`.

Ficaram de fora as tabelas da fila do Laravel (`jobs`, `job_batches`, `failed_jobs`, da migration
`0001_01_01_000002_create_jobs_table.php`) e a tabela de controle `migrations`, que não fazem
parte do domínio.

São 26 tabelas de domínio, divididas em três diagramas. A tabela `users` aparece nos três, só com
a chave primária nos dois últimos. Cada diagrama mostra as chaves e as colunas principais; as
colunas de data de criação e atualização foram omitidas quando não têm regra de negócio.

Convenções: `PK` chave primária, `FK` chave estrangeira com restrição no banco, `UK` índice
único. Relações tracejadas são lógicas, sem restrição de chave estrangeira no banco.

## 1. Usuários, acesso e planos

```mermaid
erDiagram
    users {
        int id PK
        varchar uuid UK
        varchar nome
        varchar email UK
        varchar senha_hash
        varchar telefone
        varchar nivel_ingles
        varchar idioma_preferido
        varchar status "ativo, inativo ou bloqueado"
        varchar role "user ou admin"
        varchar foto_perfil "URL do Cloudinary"
        varchar reset_token "hash SHA-256"
        timestamp reset_token_expires
        varchar stripe_customer_id UK
        timestamp ultimo_login
        timestamp email_verificado_em "migration 2026_11_19"
        varchar verificacao_token "hash SHA-256"
        timestamp verificacao_token_expira
        timestamp ultimo_lembrete_em "migration 2026_11_19"
        varchar termos_versao "migration 2026_11_20"
        timestamp termos_aceitos_em
    }
    social_profiles {
        int id PK
        int user_id FK
        varchar provider "UK com identifier"
        varchar identifier
        bytea access_token
        bytea refresh_token
    }
    roles {
        int id PK
        varchar nome UK
        int nivel
        boolean is_sistema
        boolean is_ativo
    }
    permissoes {
        int id PK
        varchar nome UK
        varchar recurso
        varchar acao
        boolean is_ativo
    }
    role_permissoes {
        int role_id PK, FK
        int permissao_id PK, FK
    }
    usuario_roles {
        int usuario_id PK, FK
        int role_id PK, FK
        int atribuido_por FK
        boolean is_manual
    }
    logs_permissoes {
        int id PK
        int usuario_id FK
        varchar acao
        varchar entidade
        int entidade_id
        jsonb dados_anteriores
        jsonb dados_novos
        varchar ip
    }
    planos {
        int id PK
        varchar nome
        int role_id FK
        numeric preco_mensal
        numeric preco_anual
        int dias_trial
        jsonb recursos
        jsonb limites "flashcards, quizes, tags, prompts"
        boolean is_ativo
        int ordem
    }
    assinaturas {
        int id PK
        int usuario_id FK
        int plano_id FK
        varchar status "pending por padrão, active, canceled"
        timestamp data_inicio
        timestamp data_fim
        timestamp data_cancelamento
        varchar payment_id "id da sessão de Checkout"
        varchar payment_gateway
        boolean is_ativo
        varchar stripe_subscription_id
        varchar stripe_status
        boolean cancelar_no_fim_periodo
    }

    users ||--o{ social_profiles : "tem"
    users ||--o{ usuario_roles : "recebe"
    roles ||--o{ usuario_roles : "atribuída em"
    users |o--o{ usuario_roles : "atribuiu"
    roles ||--o{ role_permissoes : "concede"
    permissoes ||--o{ role_permissoes : "concedida em"
    users |o--o{ logs_permissoes : "gera"
    roles |o--o{ planos : "associada a"
    users ||--o{ assinaturas : "assina"
    planos |o--o{ assinaturas : "contratado em"
```

## 2. Estudo: flashcards, quizzes, prompts e IA

```mermaid
erDiagram
    users {
        int id PK
    }
    flashcards {
        int id PK
        uuid uuid UK
        int usuario_id FK
        text frente
        text verso
        varchar nivel_dificuldade
        int repeticoes "SM-2"
        int intervalo_dias "SM-2"
        numeric fator_ease "SM-2, inicia em 2.50"
        timestamp proxima_revisao
        timestamp ultima_revisao
    }
    tags {
        int id PK
        uuid uuid UK
        int criado_por FK "UK com nome"
        varchar nome
        varchar cor
        boolean tag_sistema
    }
    flashcard_tags {
        int id PK
        int flashcard_id "sem FK no banco"
        int tag_id FK
    }
    quizes {
        int id PK
        uuid uuid UK
        int usuario_id FK
        varchar titulo
        varchar tipo_criacao
        varchar nivel_dificuldade
        int total_questoes
        int tempo_limite
        boolean publico
    }
    quiz_questoes {
        int id PK
        int quiz_id FK
        varchar tipo "multipla_escolha e outros"
        text enunciado
        text resposta_esperada
        text explicacao
        int ordem
    }
    quiz_alternativas {
        int id PK
        int questao_id FK
        text texto
        boolean correta
        int ordem
    }
    respostas_usuario {
        int id PK
        int usuario_id FK
        varchar tipo "flashcard ou quiz"
        int referencia_id
        boolean correto
        timestamp criado_em
    }
    prompts {
        int id PK
        uuid uuid UK
        int usuario_id FK
        text texto_original
        varchar idioma_original
        varchar contexto
        varchar sessao_id
    }
    traducoes {
        int id PK
        int prompt_id FK
        text texto_traduzido
        varchar idioma_destino
        numeric pontuacao_confianca
        varchar servico_traducao "claude"
        jsonb traducoes_alternativas
    }
    frases_semelhantes {
        int id PK
        int prompt_id FK
        text frase_semelhante
        numeric pontuacao_semelhante
        varchar tipo_frase
        varchar nivel_dificuldade
    }
    imagens_geradas {
        int id PK
        int prompt_id FK
        int traducao_id FK
        varchar url_imagem
        text prompt_imagem
        varchar servico_geracao
    }

    users ||--o{ flashcards : "cria"
    users |o--o{ tags : "cria"
    flashcards ||..o{ flashcard_tags : "marcado por"
    tags ||--o{ flashcard_tags : "marca"
    users ||--o{ quizes : "cria"
    quizes ||--o{ quiz_questoes : "contém"
    quiz_questoes ||--o{ quiz_alternativas : "oferece"
    users ||--o{ respostas_usuario : "responde"
    users ||--o{ prompts : "escreve"
    prompts ||--o{ traducoes : "gera"
    prompts ||--o{ frases_semelhantes : "gera"
    prompts |o--o{ imagens_geradas : "ilustra"
    traducoes |o--o{ imagens_geradas : "ilustra"
```

## 3. Acompanhamento, gamificação e nivelamento

```mermaid
erDiagram
    users {
        int id PK
    }
    preferencias_usuario {
        int id PK
        int usuario_id FK, UK
        varchar tema "claro, escuro, daltônico"
        boolean modo_daltonico
        boolean notificacoes_ativas
        boolean som_ativo
        varchar preferencia_dificuldade
        int meta_diaria_minutos
        smallint meta_diaria_cards "migration 2026_11_19, padrão 10"
    }
    progresso_usuario {
        int id PK
        int usuario_id FK, UK
        int vocabulario_aprendido
        int flashcards_concluidos
        int quizes_concluidos
        int tempo_total_estudo "só o total acumulado"
        int sequencia_atual
        int maior_sequencia
        timestamp ultima_atividade
        jsonb progresso_nivel
    }
    conquistas_usuario {
        bigint id PK
        bigint usuario_id FK "UK com codigo"
        varchar codigo "uma das 8 do catálogo"
        timestamp conquistada_em
        boolean vista
    }
    pronuncia_tentativas {
        bigint id PK
        bigint usuario_id FK
        text frase
        text reconhecido
        smallint nota "0 a 100"
        timestamp criado_em
    }
    nivelamento_questoes {
        bigint id PK
        varchar nivel "A1 a C1"
        varchar categoria "gramatica ou vocabulario"
        text enunciado
        jsonb alternativas
        smallint correta "índice da alternativa"
    }
    nivelamentos {
        bigint id PK
        bigint usuario_id FK
        varchar nivel_atual
        smallint acertos_seguidos
        smallint erros_seguidos
        jsonb respostas "questao_id, nivel, correta"
        bigint questao_atual_id "sem FK no banco"
        varchar nivel_resultado
        timestamp iniciado_em
        timestamp concluido_em
    }

    users ||--o| preferencias_usuario : "configura"
    users ||--o| progresso_usuario : "acumula"
    users ||--o{ conquistas_usuario : "conquista"
    users ||--o{ pronuncia_tentativas : "pratica"
    users ||--o{ nivelamentos : "faz"
    nivelamento_questoes ||..o{ nivelamentos : "questão atual"
```

## Observações sobre o esquema

- **Exclusão em cascata:** quase todas as FKs para `users` são `ON DELETE CASCADE`, o que sustenta
  a exclusão da conta (LGPD). As exceções são `tags.criado_por` (`SET NULL`), `logs_permissoes` e
  `usuario_roles.atribuido_por` (sem ação). `imagens_geradas` usa `SET NULL` para prompt e
  tradução.
- **Repetição espaçada:** o estado do SM-2 fica no próprio flashcard (`repeticoes`,
  `intervalo_dias`, `fator_ease`, `proxima_revisao`, `ultima_revisao`).
- **`respostas_usuario`:** relação polimórfica. `tipo` (`flashcard` ou `quiz`) diz se `referencia_id`
  aponta para um flashcard ou um quiz, por isso não há FK. No quiz, grava uma linha por questão
  respondida. É a base dos gráficos de revisões e acerto.
- **Tokens:** os tokens de verificação de e-mail e de recuperação de senha ficam só como hash
  SHA-256. O refresh token JWT não é gravado no banco; a revogação fica no cache
  (ver `sequencias.md`). As colunas `users.token` e `users.token_expires` vêm do esquema herdado.
- **Tags:** o nome é único por dono (`tags_criado_por_nome_key`) e, entre as tags sem dono, único
  globalmente (`tags_sem_dono_nome_key`). Os dois são índices parciais.
- **Tempo de estudo:** `progresso_usuario.tempo_total_estudo` guarda só o total. Não há registro
  por sessão, por isso o gráfico de tempo por semana ficou para trabalhos futuros.

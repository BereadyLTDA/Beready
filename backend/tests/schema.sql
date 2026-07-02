-- Test database schema.
--
-- If you are not using CakePHP migrations you can put
-- your application's schema in this file and use it in tests.
a-- USERS
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    senha_hash VARCHAR(255),
    telefone VARCHAR(20),
    nivel_ingles VARCHAR(20) DEFAULT 'iniciante',
    idioma_preferido VARCHAR(10) DEFAULT 'pt-BR',
    objetivos_aprendizado TEXT,
    status VARCHAR(20) DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login TIMESTAMP,
    token VARCHAR(255),
    token_expires TIMESTAMP
);

-- PREFERENCIAS USUARIO
CREATE TABLE IF NOT EXISTS preferencias_usuario (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER UNIQUE,
    tema VARCHAR(20) DEFAULT 'claro',
    modo_daltonico BOOLEAN DEFAULT false,
    notificacoes_ativas BOOLEAN DEFAULT true,
    som_ativo BOOLEAN DEFAULT true,
    traducao_automatica BOOLEAN DEFAULT true,
    preferencia_dificuldade VARCHAR(20) DEFAULT 'adaptativo',
    meta_diaria_minutos INTEGER DEFAULT 30,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PROMPTS
CREATE TABLE IF NOT EXISTS prompts (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER,
    texto_original TEXT,
    idioma_original VARCHAR(10) DEFAULT 'pt-BR',
    contexto VARCHAR(20) DEFAULT 'manual',
    midia_origem_id INTEGER,
    sessao_id VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TRADUCOES
CREATE TABLE IF NOT EXISTS traducoes (
    id SERIAL PRIMARY KEY,
    prompt_id INTEGER,
    texto_traduzido TEXT,
    idioma_destino VARCHAR(10) DEFAULT 'en',
    pontuacao_confianca DECIMAL(3,2),
    servico_traducao VARCHAR(50),
    traducoes_alternativas JSONB,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE
);

-- IMAGENS GERADAS
CREATE TABLE IF NOT EXISTS imagens_geradas (
    id SERIAL PRIMARY KEY,
    prompt_id INTEGER,
    traducao_id INTEGER,
    url_imagem VARCHAR(500),
    prompt_imagem TEXT,
    servico_geracao VARCHAR(50),
    qualidade_imagem VARCHAR(20) DEFAULT 'media',
    tamanho_arquivo INTEGER,
    dimensoes VARCHAR(20),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE SET NULL,
    FOREIGN KEY (traducao_id) REFERENCES traducoes(id) ON DELETE SET NULL
);

-- FRASES SEMELHANTES
CREATE TABLE IF NOT EXISTS frases_semelhantes (
    id SERIAL PRIMARY KEY,
    prompt_id INTEGER,
    frase_semelhante TEXT,
    pontuacao_semelhante DECIMAL(3,2),
    tipo_frase VARCHAR(20) DEFAULT 'relacionada',
    nivel_dificuldade VARCHAR(20) DEFAULT 'iniciante',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE
);

-- TAGS
CREATE TABLE IF NOT EXISTS tags (
    id SERIAL PRIMARY KEY,
    criado_por INTEGER,
    nome VARCHAR(100) UNIQUE,
    cor VARCHAR(7),
    descricao TEXT,
    tag_sistema BOOLEAN DEFAULT false,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES users(id) ON DELETE SET NULL
);

-- QUIZES
CREATE TABLE IF NOT EXISTS quizes (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER,
    titulo VARCHAR(200),
    descricao TEXT,
    tipo_criacao VARCHAR(20) DEFAULT 'ia_gerado',
    nivel_dificuldade VARCHAR(20) DEFAULT 'iniciante',
    total_questoes INTEGER,
    tempo_limite INTEGER,
    publico BOOLEAN DEFAULT false,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PROGRESSO USUARIO
CREATE TABLE IF NOT EXISTS progresso_usuario (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER UNIQUE,
    vocabulario_aprendido INTEGER DEFAULT 0,
    flashcards_concluidos INTEGER DEFAULT 0,
    quizes_concluidos INTEGER DEFAULT 0,
    tempo_total_estudo INTEGER DEFAULT 0,
    sequencia_atual INTEGER DEFAULT 0,
    maior_sequencia INTEGER DEFAULT 0,
    ultima_atividade TIMESTAMP,
    progresso_nivel JSONB,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
);

-- FLASHCARD TAGS
CREATE TABLE IF NOT EXISTS flashcard_tags (
    id SERIAL PRIMARY KEY,
    flashcard_id INTEGER,
    tag_id INTEGER,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- FLASHCARDS
CREATE TABLE IF NOT EXISTS flashcards (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER,
    frente TEXT,
    verso TEXT,
    nivel_dificuldade VARCHAR(20) DEFAULT 'iniciante',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
);
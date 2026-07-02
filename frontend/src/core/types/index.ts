export interface User {
  id: number
  nome: string
  email: string
  senha_hash?: string
  telefone?: string
  nivel_ingles: string
  idioma_preferido: string
  objetivos_aprendizado?: string
  status: string
  criado_em?: string
  atualizado_em?: string
  ultimo_login?: string
}

export interface Tag {
  id: number
  criado_por: number
  nome: string
  cor: string
  descricao?: string
  tag_sistema: boolean
  criado_em: string
}

export interface Prompt {
  id: number
  usuario_id: number
  texto_original: string
  idioma_original: string
  contexto: string
  midia_origem_id?: number
  sessao_id?: string
  criado_em: string
}

export interface Traducao {
  id: number
  prompt_id: number
  texto_traduzido: string
  idioma_destino: string
  pontuacao_confianca: number
  servico_traducao?: string
  traducoes_alternativas?: string[]
  criado_em: string
}

export interface Imagem {
  id: number
  prompt_id: number
  traducao_id?: number
  url_imagem: string
  prompt_imagem?: string
  servico_geracao?: string
  qualidade_imagem?: string
  tamanho_arquivo?: number
  dimensoes?: string
  criado_em: string
}

export interface Frase {
  id: number
  prompt_id: number
  frase_semelhante: string
  pontuacao_semelhante: number
  tipo_frase: string
  nivel_dificuldade: string
  criado_em: string
}

export interface Flashcard {
  id: number
  usuario_id: number
  frente: string
  verso: string
  nivel_dificuldade: string
  criado_em: string
  atualizado_em: string
}

export interface Quiz {
  id: number
  usuario_id: number
  titulo: string
  descricao?: string
  tipo_criacao: string
  nivel_dificuldade: string
  total_questoes: number
  tempo_limite?: number
  publico: boolean
  criado_em: string
  atualizado_em: string
}

export interface Progresso {
  id: number
  usuario_id: number
  vocabulario_aprendido: number
  flashcards_concluidos: number
  quizes_concluidos: number
  tempo_total_estudo: number
  sequencia_atual: number
  maior_sequencia: number
  ultima_atividade?: string
  progresso_nivel?: Record<string, unknown>
  atualizado_em: string
}

export interface Preferencia {
  id?: number
  usuario_id: number
  tema: 'claro' | 'escuro'
  modo_daltonico: boolean
  notificacoes_ativas: boolean
  som_ativo: boolean
  traducao_automatica: boolean
  preferencia_dificuldade: 'iniciante' | 'intermediario' | 'avancado' | 'adaptativo'
  meta_diaria_minutos: number
  criado_em?: string
  atualizado_em?: string
}

export interface ApiResponse<T = unknown> {
  success: boolean
  message?: string
  data: T
  errors?: Record<string, string[]>
}

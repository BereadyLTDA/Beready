import type { NivelDificuldade } from '@/shared/utils/nivelDificuldade'

export type { NivelDificuldade }

export type UserRole = 'user' | 'admin'

// Usuário como devolvido pelo backend (nunca contém dados sensíveis como hash de senha)
export interface User {
  id: number
  nome: string
  email: string
  role: UserRole
  telefone?: string | null
  nivel_ingles?: string
  idioma_preferido?: string
  objetivos_aprendizado?: string | null
  status?: string
  foto_perfil?: string | null
  uuid?: string
  criado_em?: string
  atualizado_em?: string
  ultimo_login?: string | null
}

export interface AuthTokens {
  access_token: string
  expires_in: number
  token_type: string
}

// POST /auth/login e POST /auth/social/exchange
export interface LoginResponseData {
  user: User
  tokens: AuthTokens
}

// POST /auth/refresh
export interface RefreshResponseData extends AuthTokens {
  user?: User
}

export interface Tag {
  id: number
  criado_por: number
  nome: string
  cor: string
  descricao?: string
  tag_sistema?: boolean
  criado_em?: string
}

export type TagInput = Omit<Tag, 'id' | 'criado_em'>

export interface Prompt {
  id: number
  usuario_id?: number
  texto_original: string
  idioma_original: string
  contexto: string
  midia_origem_id?: number
  sessao_id?: string
  criado_em: string
}

export type PromptInput = Omit<Prompt, 'id' | 'criado_em'>

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
  prompt_id?: number
  frase_id?: number
  frente: string
  verso: string
  nivel_dificuldade: NivelDificuldade
  // Repetição espaçada (SM-2): calculados pelo backend, nunca enviados
  ultima_revisao?: string | null
  proxima_revisao?: string
  intervalo_dias?: number
  fator_ease?: number
  repeticoes?: number
  criado_em?: string
  atualizado_em?: string
}

export type FlashcardInput = Omit<
  Flashcard,
  'id' | 'criado_em' | 'atualizado_em' | 'ultima_revisao' | 'proxima_revisao' | 'intervalo_dias' | 'fator_ease' | 'repeticoes'
>

// Avaliação do estudo -> nota do SM-2 no backend (1 / 4 / 5)
export type NotaRevisao = 'errei' | 'bom' | 'facil'

// GET /flashcards/revisao
export interface FlashcardsDevidos {
  total: number
  flashcards: Flashcard[]
}

export interface FlashcardData {
  frente: string
  verso: string
  nivel_dificuldade?: string
  usuario_id?: number
}

export interface Quiz {
  id: number
  usuario_id: number
  titulo: string
  descricao?: string | null
  tipo_criacao: string
  nivel_dificuldade: NivelDificuldade
  total_questoes: number
  tempo_limite?: number | null
  publico: boolean
  criado_em: string
  atualizado_em: string
  // GET /quizes/{id}: questões sem gabarito
  questoes?: QuizQuestao[]
}

export type QuizQuestaoTipo = 'multipla_escolha' | 'completar'

export interface QuizAlternativa {
  id: number
  texto: string
  ordem: number
  // Só no editor do dono (GET /quizes/{id}/questoes)
  correta?: boolean
}

export interface QuizQuestao {
  id: number
  quiz_id: number
  tipo: QuizQuestaoTipo
  enunciado: string
  ordem: number
  alternativas: QuizAlternativa[]
  // Só no editor do dono
  resposta_esperada?: string | null
  explicacao?: string | null
}

export interface QuizQuestaoInput {
  tipo: QuizQuestaoTipo
  enunciado: string
  resposta_esperada?: string | null
  explicacao?: string | null
  alternativas?: { texto: string; correta: boolean }[]
}

// Resposta de uma questão enviada ao servidor
export interface QuizRespostaQuestao {
  questao_id: number
  alternativa_id?: number
  resposta?: string
}

// Correção feita pelo servidor
export interface QuizCorrecao {
  questao_id: number
  tipo: QuizQuestaoTipo
  correta: boolean
  respondida: boolean
  alternativa_id: number | null
  resposta: string | null
  alternativa_correta_id: number | null
  resposta_esperada: string | null
  explicacao: string | null
}

// POST /quizes/{id}/finalizar
export interface QuizResultado {
  quiz_id: number
  total: number
  acertos: number
  erros: number
  percentual: number
  correcao: QuizCorrecao[]
}

// POST /quizes/gerar
export interface GerarQuizInput {
  quantidade: number
  nivel?: NivelDificuldade
  tag_id?: number
  titulo?: string
}

export interface Progresso {
  id?: number
  usuario_id: number
  vocabulario_aprendido?: number
  flashcards_concluidos?: number
  quizes_concluidos?: number
  tempo_total_estudo?: number
  sequencia_atual?: number
  maior_sequencia?: number
  ultima_atividade?: string
  progresso_nivel?: Record<string, unknown>
  // Ainda não implementados no backend; reconhecidos automaticamente quando vierem
  taxa_acerto?: number
  progresso_geral?: number
  atualizado_em?: string
}

export interface Preferencia {
  id?: number
  usuario_id: number
  tema: 'claro' | 'escuro'
  modo_daltonico: boolean
  notificacoes_ativas: boolean
  som_ativo: boolean
  traducao_automatica: boolean
  preferencia_dificuldade: NivelDificuldade | 'adaptativo'
  meta_diaria_minutos: number
  meta_diaria_cards?: number
  criado_em?: string
  atualizado_em?: string
}

export interface ApiResponse<T = unknown> {
  success: boolean
  message?: string
  data: T
  errors?: Record<string, string[]>
}
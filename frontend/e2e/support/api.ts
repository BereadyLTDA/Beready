import type { Page, Request, Route } from '@playwright/test'
import type { Flashcard, Preferencia, Progresso, Quiz, QuizCorrecao, QuizQuestao, Tag, User } from '../../src/core/types'
import {
  type AssinaturaFixture,
  type PlanoFixture,
  makeAssinaturaGratuita,
  makePlanos,
  makePreferencias,
  makeProgresso,
  makeTags,
  makeUser,
  userPermissions,
} from './data'

/**
 * Mock da API do BeReady para os testes E2E.
 *
 * Intercepta (page.route) toda requisição para API_URL e responde no formato real do
 * backend: `{ success, message, data }` / `{ success: false, message, errors? }`.
 * Os handlers padrão são "com estado" (criar flashcard aparece na lista, cancelar a
 * assinatura muda o GET /user/assinatura etc.). Cada teste pode sobrescrever uma rota
 * com `api.on(method, path, handler)`; a sobrescrita mais recente vence.
 * Rotas não mockadas respondem 404 e ficam em `api.unhandled` (útil para depurar).
 */

export const API_URL = process.env.E2E_API_URL ?? 'http://localhost:8765'

export interface MockReply {
  status?: number
  body?: unknown
  contentType?: string
  headers?: Record<string, string>
}

export const ok = (data: unknown = null, message = 'success', status = 200): MockReply => ({
  status,
  body: { success: true, message, data },
})

export const fail = (status: number, message: string, errors?: Record<string, unknown>): MockReply => ({
  status,
  body: { success: false, message, ...(errors ? { errors } : {}) },
})

// Quiz do mock: as questões ficam COM gabarito; as rotas de jogar devolvem sem ele
export interface QuizMock extends Quiz {
  questoes: QuizQuestao[]
}

export interface MockState {
  /** null = visitante (POST /auth/refresh -> 401) */
  user: User | null
  accessToken: string
  permissions: string[]
  planos: PlanoFixture[]
  /** null = sem assinatura (GET /user/assinatura -> 404) */
  assinatura: AssinaturaFixture | null
  /** null = usuário sem preferências salvas (404) */
  preferencias: Preferencia | null
  progresso: Progresso
  flashcards: Flashcard[]
  quizes: QuizMock[]
  tags: Tag[]
  metaDiaria: { meta: number; feitos_hoje: number; sequencia_atual: number }
  conquistas: ConquistaMock[]
  /** true = o aluno já fez o nivelamento (o dashboard não mostra o convite) */
  nivelamentoFeito: boolean
}

export interface ConquistaMock {
  codigo: string
  icone: string
  alvo: number
  atual: number
  conquistada: boolean
  conquistada_em: string | null
  nova: boolean
}

/** Catálogo como o backend devolve, com nada conquistado */
export const makeConquistas = (): ConquistaMock[] =>
  [
    ['primeiro_card', 'document', 1],
    ['cards_50', 'refresh', 50],
    ['cards_200', 'star', 200],
    ['sequencia_7', 'clock', 7],
    ['sequencia_30', 'chart-bar', 30],
    ['quiz_gabaritado', 'check-circle', 1],
    ['frases_ia_10', 'chat', 10],
    ['pronuncia_90', 'microphone', 90],
  ].map(([codigo, icone, alvo]) => ({
    codigo: String(codigo),
    icone: String(icone),
    alvo: Number(alvo),
    atual: 0,
    conquistada: false,
    conquistada_em: null,
    nova: false,
  }))

export interface HandlerContext {
  request: Request
  url: URL
  params: Record<string, string>
  body: Record<string, unknown>
  state: MockState
}

export type Handler = (ctx: HandlerContext) => MockReply | Promise<MockReply>

interface RouteDef {
  method: string
  pattern: RegExp
  keys: string[]
  handler: Handler
}

export interface RecordedRequest {
  method: string
  path: string
  body: Record<string, unknown>
  headers: Record<string, string>
}

const compile = (path: string): { pattern: RegExp; keys: string[] } => {
  const keys: string[] = []
  const source = path
    .split('/')
    .map((part) => {
      if (part.startsWith(':')) {
        keys.push(part.slice(1))
        return '([^/]+)'
      }
      return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    })
    .join('/')
  return { pattern: new RegExp(`^${source}/?$`), keys }
}

const parseBody = (request: Request): Record<string, unknown> => {
  try {
    const data: unknown = request.postDataJSON()
    return data && typeof data === 'object' ? (data as Record<string, unknown>) : {}
  } catch {
    return {}
  }
}

const normalizar = (texto: string): string =>
  texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase()

/** Quiz como o GET /quizes/{id} devolve: questões sem gabarito. */
const semGabarito = (quiz: QuizMock): Quiz => ({
  ...quiz,
  total_questoes: quiz.questoes.length,
  questoes: quiz.questoes.map((q) => ({
    id: q.id,
    quiz_id: q.quiz_id,
    tipo: q.tipo,
    enunciado: q.enunciado,
    ordem: q.ordem,
    alternativas: q.alternativas.map((a) => ({ id: a.id, texto: a.texto, ordem: a.ordem })),
  })),
})

const resumoQuiz = (quiz: QuizMock): Quiz => {
  const { questoes, ...rest } = quiz
  return { ...rest, total_questoes: questoes.length }
}

const corrigir = (questao: QuizQuestao, resposta: { alternativa_id?: unknown; resposta?: unknown }): QuizCorrecao => {
  const correta = questao.alternativas.find((a) => a.correta)
  const alternativaId = resposta.alternativa_id == null ? null : Number(resposta.alternativa_id)
  const texto = resposta.resposta == null ? null : String(resposta.resposta)
  const acertou =
    questao.tipo === 'completar'
      ? texto !== null && normalizar(texto) === normalizar(questao.resposta_esperada ?? '')
      : alternativaId !== null && alternativaId === correta?.id
  return {
    questao_id: questao.id,
    tipo: questao.tipo,
    correta: acertou,
    respondida: alternativaId !== null || (texto !== null && texto !== ''),
    alternativa_id: alternativaId,
    resposta: texto,
    alternativa_correta_id: questao.tipo === 'multipla_escolha' ? (correta?.id ?? null) : null,
    resposta_esperada: questao.tipo === 'completar' ? (questao.resposta_esperada ?? null) : null,
    explicacao: questao.explicacao ?? null,
  }
}

export class ApiMock {
  readonly state: MockState
  readonly requests: RecordedRequest[] = []
  readonly unhandled: string[] = []
  private readonly overrides: RouteDef[] = []
  private readonly defaults: RouteDef[] = []
  private nextId = 500

  constructor(private readonly page: Page) {
    this.state = {
      user: null,
      accessToken: 'e2e-access-token',
      permissions: [],
      planos: makePlanos(),
      assinatura: null,
      preferencias: null,
      progresso: makeProgresso(),
      flashcards: [],
      quizes: [],
      tags: makeTags(),
      metaDiaria: { meta: 10, feitos_hoje: 0, sequencia_atual: 0 },
      conquistas: makeConquistas(),
      nivelamentoFeito: false,
    }
    this.registerDefaults()
  }

  // ------------------------------------------------------------------ cenários

  /** Visitante: POST /auth/refresh -> 401 (sem cookie de refresh). */
  asAnonymous(): this {
    this.state.user = null
    this.state.permissions = []
    this.state.assinatura = null
    return this
  }

  /**
   * Sessão válida: o boot (POST /auth/refresh) devolve access_token + user, e as rotas
   * de contexto (permissões, assinatura, preferências, progresso) passam a responder.
   */
  asUser(
    user: User = makeUser(),
    options: {
      permissions?: string[]
      assinatura?: AssinaturaFixture | null
      preferencias?: Preferencia | null
    } = {},
  ): this {
    this.state.user = user
    this.state.permissions = options.permissions ?? (user.role === 'admin' ? ['admin.access'] : userPermissions())
    this.state.assinatura =
      options.assinatura === undefined ? makeAssinaturaGratuita({ usuario_id: user.id }) : options.assinatura
    this.state.preferencias = options.preferencias === undefined ? null : options.preferencias
    this.state.progresso = makeProgresso({ usuario_id: user.id })
    return this
  }

  withPreferencias(overrides: Partial<Preferencia> = {}): this {
    this.state.preferencias = makePreferencias({ usuario_id: this.state.user?.id ?? 1, ...overrides })
    return this
  }

  withNivelamentoFeito(): this {
    this.state.nivelamentoFeito = true
    return this
  }

  withConquistas(conquistas: ConquistaMock[]): this {
    this.state.conquistas = conquistas
    return this
  }

  withMetaDiaria(meta: Partial<MockState['metaDiaria']>): this {
    this.state.metaDiaria = { ...this.state.metaDiaria, ...meta }
    return this
  }

  withFlashcards(flashcards: Flashcard[]): this {
    this.state.flashcards = flashcards
    return this
  }

  withQuizes(quizes: QuizMock[]): this {
    this.state.quizes = quizes
    return this
  }

  // ------------------------------------------------------------------ API pública

  /** Sobrescreve uma rota (path no estilo "/flashcards/:id/revisao"). */
  on(method: string, path: string, handler: Handler | MockReply): this {
    const { pattern, keys } = compile(path)
    const fn: Handler = typeof handler === 'function' ? handler : () => handler
    this.overrides.unshift({ method: method.toUpperCase(), pattern, keys, handler: fn })
    return this
  }

  /** Espera a próxima requisição para `method path` (path exato, sem query string). */
  waitFor(method: string, path: string | RegExp): Promise<Request> {
    return this.page.waitForRequest((request) => {
      if (request.method() !== method.toUpperCase()) return false
      const { pathname } = new URL(request.url())
      if (!request.url().startsWith(API_URL)) return false
      return typeof path === 'string' ? pathname === path : path.test(pathname)
    })
  }

  /** Requisições já feitas para `method path`. */
  calls(method: string, path: string): RecordedRequest[] {
    return this.requests.filter((r) => r.method === method.toUpperCase() && r.path === path)
  }

  async install(): Promise<void> {
    await this.page.route(
      (url) => url.href.startsWith(API_URL),
      (route) => this.handle(route),
    )
  }

  // ------------------------------------------------------------------ internos

  private async handle(route: Route): Promise<void> {
    const request = route.request()
    const url = new URL(request.url())
    const method = request.method().toUpperCase()
    const origin = (await request.headerValue('origin')) ?? '*'
    const cors: Record<string, string> = {
      'access-control-allow-origin': origin,
      'access-control-allow-credentials': 'true',
      'access-control-allow-methods': 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
      'access-control-allow-headers':
        (await request.headerValue('access-control-request-headers')) ?? 'authorization,content-type,accept',
      vary: 'Origin',
    }

    if (method === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: cors })
      return
    }

    const body = parseBody(request)
    this.requests.push({ method, path: url.pathname, body, headers: request.headers() })

    const match = this.match(method, url.pathname)
    let reply: MockReply
    if (match) {
      reply = await match.def.handler({ request, url, params: match.params, body, state: this.state })
    } else {
      this.unhandled.push(`${method} ${url.pathname}`)
      reply = fail(404, `Rota não mockada no E2E: ${method} ${url.pathname}`)
    }

    const contentType = reply.contentType ?? 'application/json'
    await route.fulfill({
      status: reply.status ?? 200,
      headers: { ...cors, ...(reply.headers ?? {}) },
      contentType,
      body:
        reply.body === undefined
          ? ''
          : typeof reply.body === 'string'
            ? reply.body
            : JSON.stringify(reply.body),
    })
  }

  private match(method: string, path: string): { def: RouteDef; params: Record<string, string> } | null {
    for (const def of [...this.overrides, ...this.defaults]) {
      if (def.method !== method) continue
      const result = def.pattern.exec(path)
      if (!result) continue
      const params: Record<string, string> = {}
      def.keys.forEach((key, i) => {
        params[key] = decodeURIComponent(result[i + 1] ?? '')
      })
      return { def, params }
    }
    return null
  }

  private def(method: string, path: string, handler: Handler): void {
    const { pattern, keys } = compile(path)
    this.defaults.push({ method, pattern, keys, handler })
  }

  private id(): number {
    return ++this.nextId
  }

  private findQuiz(id: string): QuizMock | undefined {
    return this.state.quizes.find((q) => q.id === Number(id))
  }

  private registerDefaults(): void {
    const s = this.state
    const requireUser = (fn: Handler): Handler => (ctx) =>
      s.user ? fn(ctx) : fail(401, 'Token não fornecido')

    // ---------------- auth
    this.def('POST', '/auth/refresh', () =>
      s.user
        ? ok({ access_token: s.accessToken, expires_in: 900, token_type: 'Bearer', user: s.user }, 'Token renovado com sucesso')
        : fail(401, 'Refresh token inválido ou expirado'),
    )
    this.def('POST', '/auth/logout', () => {
      s.user = null
      return ok(null, 'Logout realizado com sucesso')
    })
    this.def('POST', '/auth/login', () => fail(401, 'E-mail ou senha inválidos'))
    this.def('POST', '/auth/register', ({ body }) =>
      ok({ user: makeUser({ id: this.id(), nome: String(body.nome ?? ''), email: String(body.email ?? '') }) }, 'Registro realizado com sucesso', 201),
    )
    this.def('POST', '/auth/resend-verification', () =>
      ok(null, 'Se a conta existir e ainda não estiver confirmada, enviamos um novo link.'),
    )
    this.def('POST', '/auth/verify-email', ({ body }) =>
      body.token === 'token-valido' ? ok(null, 'E-mail confirmado.') : fail(400, 'Link de confirmação inválido ou expirado'),
    )
    // Login social: o navegador é redirecionado para cá; basta uma página qualquer
    this.def('GET', '/auth/login/:provider', ({ params }) => ({
      status: 200,
      contentType: 'text/html',
      body: `<!doctype html><title>OAuth ${params.provider}</title><h1>OAuth ${params.provider} (mock)</h1>`,
    }))
    this.def('GET', '/users/me', requireUser(() => ok(s.user)))
    this.def(
      'PUT',
      '/users/update/:id',
      requireUser(({ body }) => {
        s.user = { ...(s.user as User), ...(body as Partial<User>) }
        return ok({ user: s.user }, 'Perfil atualizado com sucesso')
      }),
    )

    // ---------------- permissões / planos / assinatura
    this.def('GET', '/user/permissions', requireUser(() => ok(s.permissions)))
    this.def(
      'GET',
      '/user/assinatura',
      requireUser(() => (s.assinatura ? ok(s.assinatura) : fail(404, 'Nenhuma assinatura ativa'))),
    )
    this.def('GET', '/planos', () => ok(s.planos))
    this.def(
      'POST',
      '/planos/:id/assinar',
      requireUser(({ params }) => {
        const plano = s.planos.find((p) => p.id === Number(params.id))
        if (!plano) return fail(404, 'Plano não encontrado')
        if (Number(plano.preco_mensal) > 0) {
          return ok({ requires_payment: true, checkout_url: 'https://checkout.stripe.com/c/pay/e2e' })
        }
        s.assinatura = makeAssinaturaGratuita({ usuario_id: s.user?.id ?? 1, plano_id: plano.id, plano })
        return ok({ requires_payment: false, checkout_url: null, assinatura: s.assinatura }, 'Plano ativado')
      }),
    )
    this.def(
      'POST',
      '/planos/cancelar',
      requireUser(() => {
        const atual = s.assinatura
        if (atual?.recorrente) {
          s.assinatura = { ...atual, cancelar_no_fim_periodo: true }
          return ok({ assinatura: s.assinatura, cancelamento_agendado: true, ativo_ate: atual.data_fim })
        }
        s.assinatura = makeAssinaturaGratuita({ usuario_id: s.user?.id ?? 1 })
        return ok({ assinatura: s.assinatura, cancelamento_agendado: false, ativo_ate: null })
      }),
    )
    this.def(
      'POST',
      '/planos/portal',
      requireUser(() => ok({ url: 'https://billing.stripe.com/p/session/e2e_test' })),
    )

    // ---------------- preferências / progresso
    this.def(
      'GET',
      '/preferencias/usuario/:id',
      requireUser(() => (s.preferencias ? ok(s.preferencias) : fail(404, 'Preferências não encontradas'))),
    )
    this.def(
      'POST',
      '/preferencias',
      requireUser(({ body }) => {
        s.preferencias = { ...makePreferencias(), ...(s.preferencias ?? {}), ...(body as Partial<Preferencia>) }
        return ok(s.preferencias, 'Preferências salvas')
      }),
    )
    this.def('GET', '/progresso/usuario/:id', requireUser(() => ok(s.progresso)))
    this.def('POST', '/progresso/incrementar-tempo', requireUser(() => ok(s.progresso)))

    // ---------------- nivelamento (mock com 3 questões; no backend são 12 e a regra é adaptativa)
    const niv = { respondidas: 0, acertos: 0 }
    const questaoNiv = (n: number) => ({
      id: 100 + n,
      enunciado: `Question ${n + 1}: She ___ a teacher.`,
      alternativas: ['is', 'are', 'am', 'be'],
    })
    const resultadoNiv = () => ({
      nivel: niv.acertos >= 3 ? 'B2' : 'A2',
      nivel_ingles: niv.acertos >= 3 ? 'intermediario' : 'iniciante',
      acertos: niv.acertos,
      total: 3,
      concluido_em: '2026-11-20T10:00:00-03:00',
      pode_refazer_em: '2026-12-20T10:00:00-03:00',
    })
    const estadoNiv = () => {
      const concluido = niv.respondidas >= 3
      return {
        id: 1,
        concluido,
        respondidas: niv.respondidas,
        total: 3,
        questao: concluido ? null : questaoNiv(niv.respondidas),
        resultado: concluido ? resultadoNiv() : null,
      }
    }
    this.def('GET', '/nivelamento/resultado', requireUser(() => ok(s.nivelamentoFeito ? resultadoNiv() : null)))
    this.def('POST', '/nivelamento/iniciar', requireUser(() => ok(estadoNiv())))
    this.def('POST', '/nivelamento/:id/responder', requireUser(({ body }) => {
      const correta = body.alternativa === 0
      niv.respondidas++
      if (correta) niv.acertos++
      return ok({ correta, ...estadoNiv() })
    }))

    // ---------------- gamificação (as regras reais ficam no backend)
    this.def('GET', '/meta-diaria', requireUser(() => ok(s.metaDiaria)))
    this.def('GET', '/conquistas', requireUser(() => ok(s.conquistas)))
    this.def('POST', '/conquistas/vistas', requireUser(() => {
      s.conquistas = s.conquistas.map((c) => ({ ...c, nova: false }))
      return ok(null, 'Conquistas marcadas como vistas')
    }))

    // ---------------- pronúncia (a nota real é calculada pelo backend; aqui, só igual ou diferente)
    const pronuncia = { notas: [] as number[] }
    this.def('GET', '/pronuncia/frases', requireUser(() =>
      ok([
        { texto: 'Where is the station?', origem: 'prompt' },
        { texto: 'Nice to meet you.', origem: 'basica' },
      ]),
    ))
    this.def('POST', '/pronuncia/tentativas', requireUser(({ body }) => {
      const limpar = (v: unknown) => String(v ?? '').toLowerCase().replace(/[^a-z' ]/g, '').trim()
      const igual = limpar(body.frase) === limpar(body.reconhecido)
      const nota = igual ? 100 : 67
      pronuncia.notas.push(nota)
      const tentativa = { id: pronuncia.notas.length, usuario_id: 1, frase: String(body.frase), reconhecido: String(body.reconhecido), nota, criado_em: new Date().toISOString() }
      return ok({ tentativa, nota, faltando: igual ? [] : ['station'] }, 'Tentativa registrada', 201)
    }))
    this.def('GET', '/pronuncia/resumo', requireUser(() => {
      const n = pronuncia.notas
      return ok({
        total: n.length,
        media: n.length ? Math.round(n.reduce((a, b) => a + b, 0) / n.length) : 0,
        melhor: n.length ? Math.max(...n) : 0,
        recentes: [],
      })
    }))

    // ---------------- tags
    this.def('GET', '/tags', requireUser(() => ok(s.tags)))

    // ---------------- flashcards (rotas específicas antes de /flashcards/:id)
    this.def(
      'GET',
      '/flashcards/revisao',
      requireUser(({ url }) => {
        const agora = Date.now()
        const devidos = s.flashcards.filter((f) => !f.proxima_revisao || new Date(f.proxima_revisao).getTime() <= agora)
        const limite = Number(url.searchParams.get('limite')) || undefined
        return ok({ total: devidos.length, flashcards: limite ? devidos.slice(0, limite) : devidos })
      }),
    )
    this.def(
      'POST',
      '/flashcards/:id/revisao',
      requireUser(({ params, body }) => {
        const card = s.flashcards.find((f) => f.id === Number(params.id))
        if (!card) return fail(404, 'Flashcard não encontrado')
        const dias = body.nota === 'errei' ? 0 : body.nota === 'bom' ? 3 : 7
        // "Errei" volta em 10 minutos; Bom/Fácil saem da fila do dia
        card.proxima_revisao = new Date(Date.now() + (dias === 0 ? 600_000 : dias * 86_400_000)).toISOString()
        card.ultima_revisao = new Date().toISOString()
        return ok(card, 'Revisão registrada')
      }),
    )
    this.def('GET', '/flashcards', requireUser(() => ok(s.flashcards)))
    this.def(
      'POST',
      '/flashcards',
      requireUser(({ body }) => {
        const card: Flashcard = {
          id: this.id(),
          usuario_id: s.user?.id ?? 1,
          frente: String(body.frente ?? ''),
          verso: String(body.verso ?? ''),
          nivel_dificuldade: (body.nivel_dificuldade as Flashcard['nivel_dificuldade']) ?? 'iniciante',
          proxima_revisao: new Date().toISOString(),
          criado_em: new Date().toISOString(),
        }
        s.flashcards.unshift(card)
        return ok(card, 'Flashcard criado com sucesso', 201)
      }),
    )
    this.def(
      'GET',
      '/flashcards/:id',
      requireUser(({ params }) => {
        const card = s.flashcards.find((f) => f.id === Number(params.id))
        return card ? ok(card) : fail(404, 'Flashcard não encontrado')
      }),
    )
    this.def(
      'PUT',
      '/flashcards/edit/:id',
      requireUser(({ params, body }) => {
        const card = s.flashcards.find((f) => f.id === Number(params.id))
        if (!card) return fail(404, 'Flashcard não encontrado')
        Object.assign(card, body)
        return ok(card, 'Flashcard atualizado')
      }),
    )
    this.def(
      'DELETE',
      '/flashcards/delete/:id',
      requireUser(({ params }) => {
        s.flashcards = s.flashcards.filter((f) => f.id !== Number(params.id))
        return ok(null, 'Flashcard excluído')
      }),
    )

    // ---------------- quizes
    this.def('GET', '/quizes', requireUser(() => ok(s.quizes.map(resumoQuiz))))
    this.def(
      'POST',
      '/quizes/gerar',
      requireUser(({ body }) => {
        const id = this.id()
        const quiz: QuizMock = {
          id,
          usuario_id: s.user?.id ?? 1,
          titulo: String(body.titulo ?? 'Quiz dos meus flashcards'),
          descricao: null,
          tipo_criacao: 'flashcards',
          nivel_dificuldade: 'iniciante',
          total_questoes: 1,
          tempo_limite: null,
          publico: false,
          criado_em: new Date().toISOString(),
          atualizado_em: new Date().toISOString(),
          questoes: [
            {
              id: id * 10,
              quiz_id: id,
              tipo: 'multipla_escolha',
              enunciado: 'apple',
              ordem: 1,
              explicacao: null,
              alternativas: [
                { id: id * 100 + 1, texto: 'maçã', ordem: 1, correta: true },
                { id: id * 100 + 2, texto: 'livro', ordem: 2, correta: false },
                { id: id * 100 + 3, texto: 'casa', ordem: 3, correta: false },
                { id: id * 100 + 4, texto: 'gato', ordem: 4, correta: false },
              ],
            },
          ],
        }
        s.quizes.push(quiz)
        return ok(resumoQuiz(quiz), 'Quiz gerado com sucesso', 201)
      }),
    )
    this.def(
      'POST',
      '/quizes',
      requireUser(({ body }) => {
        const id = this.id()
        const quiz: QuizMock = {
          id,
          usuario_id: s.user?.id ?? 1,
          titulo: String(body.titulo ?? ''),
          descricao: (body.descricao as string) ?? null,
          tipo_criacao: 'manual',
          nivel_dificuldade: 'iniciante',
          total_questoes: 0,
          tempo_limite: null,
          publico: false,
          criado_em: new Date().toISOString(),
          atualizado_em: new Date().toISOString(),
          questoes: [],
        }
        s.quizes.push(quiz)
        return ok(resumoQuiz(quiz), 'Quiz criado com sucesso', 201)
      }),
    )
    this.def(
      'GET',
      '/quizes/:id/questoes',
      requireUser(({ params }) => {
        const quiz = this.findQuiz(params.id ?? '')
        return quiz ? ok(quiz.questoes) : fail(404, 'Quiz não encontrado')
      }),
    )
    this.def(
      'PUT',
      '/quizes/:id/questoes',
      requireUser(({ params, body }) => {
        const quiz = this.findQuiz(params.id ?? '')
        if (!quiz) return fail(404, 'Quiz não encontrado')
        const input = Array.isArray(body.questoes) ? (body.questoes as Record<string, unknown>[]) : []
        quiz.questoes = input.map((q, i) => {
          const qid = this.id()
          const alternativas = Array.isArray(q.alternativas) ? (q.alternativas as { texto: string; correta: boolean }[]) : []
          return {
            id: qid,
            quiz_id: quiz.id,
            tipo: q.tipo === 'completar' ? 'completar' : 'multipla_escolha',
            enunciado: String(q.enunciado ?? ''),
            ordem: i + 1,
            resposta_esperada: (q.resposta_esperada as string) ?? null,
            explicacao: (q.explicacao as string) ?? null,
            alternativas: alternativas.map((a, j) => ({ id: this.id(), texto: a.texto, ordem: j + 1, correta: a.correta })),
          }
        })
        return ok(quiz.questoes, 'Questões salvas com sucesso')
      }),
    )
    this.def(
      'POST',
      '/quizes/:id/questoes/:questaoId/verificar',
      requireUser(({ params, body }) => {
        const quiz = this.findQuiz(params.id ?? '')
        const questao = quiz?.questoes.find((q) => q.id === Number(params.questaoId))
        return questao ? ok(corrigir(questao, body)) : fail(404, 'Questão não encontrada')
      }),
    )
    this.def(
      'POST',
      '/quizes/:id/finalizar',
      requireUser(({ params, body }) => {
        const quiz = this.findQuiz(params.id ?? '')
        if (!quiz) return fail(404, 'Quiz não encontrado')
        const respostas = Array.isArray(body.respostas) ? (body.respostas as Record<string, unknown>[]) : []
        const correcao = quiz.questoes.map((q) => corrigir(q, respostas.find((r) => Number(r.questao_id) === q.id) ?? {}))
        const acertos = correcao.filter((c) => c.correta).length
        const total = correcao.length
        return ok(
          {
            quiz_id: quiz.id,
            total,
            acertos,
            erros: total - acertos,
            percentual: total ? Math.round((acertos / total) * 100) : 0,
            correcao,
          },
          'Tentativa registrada com sucesso',
        )
      }),
    )
    this.def(
      'GET',
      '/quizes/:id',
      requireUser(({ params }) => {
        const quiz = this.findQuiz(params.id ?? '')
        return quiz ? ok(semGabarito(quiz)) : fail(404, 'Quiz não encontrado')
      }),
    )
    this.def(
      'PUT',
      '/quizes/:id',
      requireUser(({ params, body }) => {
        const quiz = this.findQuiz(params.id ?? '')
        if (!quiz) return fail(404, 'Quiz não encontrado')
        Object.assign(quiz, body)
        return ok(resumoQuiz(quiz), 'Quiz atualizado com sucesso')
      }),
    )
    this.def(
      'DELETE',
      '/quizes/:id',
      requireUser(({ params }) => {
        s.quizes = s.quizes.filter((q) => q.id !== Number(params.id))
        return ok(null, 'Quiz excluído')
      }),
    )
  }
}

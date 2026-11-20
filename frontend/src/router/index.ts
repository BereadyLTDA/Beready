import { createRouter, createWebHistory } from 'vue-router'
import { usePermissionStore } from '@/stores/permissionStore'
import { useAuthStore } from '@/stores/auth'

// Layout (Navbar + router-view) das páginas autenticadas
const AuthenticatedLayout = () => import('../layouts/AuthenticatedLayout.vue')

const Home = () => import('../views/_global/HomePage.vue')
const DashboardPage = () => import('../views/_global/DashboardPage.vue')

const UserLogin = () => import('../modules/auth/views/UserLogin.vue')
const UserRegister = () => import('../modules/auth/views/UserRegister.vue')
const UserProfile = () => import('../modules/auth/views/UserProfile.vue')
const ProfileEdit = () => import('../modules/auth/views/ProfileEdit.vue')
const ForgotPassword = () => import('../modules/auth/views/ForgotPassword.vue')
const ResetPassword = () => import('../modules/auth/views/ResetPassword.vue')
const OAuthCallback = () => import('../modules/auth/views/OAuthCallback.vue')

const FlashcardList = () => import('../modules/flashcards/views/FlashcardList.vue')
const FlashcardView = () => import('../modules/flashcards/views/FlashcardView.vue')
const FlashcardStudy = () => import('../modules/flashcards/views/FlashcardStudy.vue')

const QuizList = () => import('../modules/quizes/views/QuizList.vue')
const QuizView = () => import('../modules/quizes/views/QuizView.vue')
const QuizPlay = () => import('../modules/quizes/views/QuizPlay.vue')
const QuizAdd = () => import('../modules/quizes/views/QuizAdd.vue')
const QuizEdit = () => import('../modules/quizes/views/QuizEdit.vue')

const PromptList = () => import('../modules/prompts/views/PromptList.vue')
const PromptDetail = () => import('../modules/prompts/views/PromptDetail.vue')
const TagList = () => import('../modules/tags/views/TagList.vue')

// CORRIGIDO: Caminho correto para ProgressDashboard
const ProgressDashboard = () => import('../modules/progresso/views/ProgressoDashboard.vue')

const UserPreferences = () => import('../modules/preferencias/views/UserPreferences.vue')
const TraducoesPrompt = () => import('../modules/traducoes/views/TraducoesPrompt.vue')
const ImagensPrompt = () => import('../modules/imagens/views/ImagensPrompt.vue')
const FrasesPrompt = () => import('../modules/frases/views/FrasesPrompt.vue')

// Admin Panel
const AdminPanel = () => import('../modules/admin/views/AdminPanel.vue')

// CORRIGIDO: Caminho correto para PlanosPage
const PlansPage = () => import('../views/PlanosPage.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Páginas públicas (sem menu)
    { path: '/', name: 'home', component: Home, meta: { requiresAuth: false } },
    { path: '/login', name: 'login', component: UserLogin, meta: { requiresAuth: false } },
    { path: '/register', name: 'register', component: UserRegister, meta: { requiresAuth: false } },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPassword,
      meta: { requiresAuth: false },
    },
    {
      path: '/termos',
      name: 'termos',
      component: () => import('../modules/legal/views/LegalPage.vue'),
      props: { tipo: 'termos' },
      meta: { requiresAuth: false },
    },
    {
      path: '/privacidade',
      name: 'privacidade',
      component: () => import('../modules/legal/views/LegalPage.vue'),
      props: { tipo: 'privacidade' },
      meta: { requiresAuth: false },
    },
    {
      path: '/verificar-email/:token',
      name: 'verificar-email',
      component: () => import('../modules/auth/views/VerificarEmail.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/reset-password/:token',
      name: 'reset-password',
      component: ResetPassword,
      meta: { requiresAuth: false },
    },
    {
      path: '/oauth-callback',
      name: 'oauth-callback',
      component: OAuthCallback,
      meta: { requiresAuth: false },
    },
    // Planos Page (Publica)
    {
      path: '/planos',
      name: 'planos',
      component: PlansPage,
      meta: { requiresAuth: false },
    },

    // Páginas autenticadas: renderizadas dentro do layout com a Navbar.
    // O registro pai não tem nome nem meta; '/' continua resolvendo para 'home'
    // (registrado antes, mesma pontuação). Os paths dos filhos são absolutos.
    {
      path: '/',
      component: AuthenticatedLayout,
      children: [
        { path: '/dashboard', name: 'dashboard', component: DashboardPage, meta: { requiresAuth: true } },
        { path: '/profile', name: 'profile', component: UserProfile, meta: { requiresAuth: true } },
        {
          path: '/profile/edit',
          name: 'profile-edit',
          component: ProfileEdit,
          meta: { requiresAuth: true },
        },
        {
          path: '/flashcards',
          name: 'flashcards',
          component: FlashcardList,
          meta: { requiresAuth: true },
        },
        // Revisão espaçada: fila dos cards vencidos (mesma tela do estudo)
        {
          path: '/flashcards/revisao',
          name: 'flashcard-review',
          component: FlashcardStudy,
          meta: { requiresAuth: true },
        },
        {
          path: '/flashcards/:id',
          name: 'flashcard-view',
          component: FlashcardView,
          meta: { requiresAuth: true },
        },
        {
          path: '/flashcards/:id/study',
          name: 'flashcard-study',
          component: FlashcardStudy,
          meta: { requiresAuth: true },
        },
        { path: '/quizes', name: 'quizes', component: QuizList, meta: { requiresAuth: true } },
        { path: '/quizes/add', name: 'quiz-add', component: QuizAdd, meta: { requiresAuth: true } },
        {
          path: '/quizes/edit/:id',
          name: 'quiz-edit',
          component: QuizEdit,
          meta: { requiresAuth: true },
        },
        {
          path: '/quizes/:id/play',
          name: 'quiz-play',
          component: QuizPlay,
          meta: { requiresAuth: true },
        },
        { path: '/quizes/:id', name: 'quiz-view', component: QuizView, meta: { requiresAuth: true } },
        { path: '/tags', name: 'tags', component: TagList, meta: { requiresAuth: true } },
        { path: '/prompts', name: 'prompts', component: PromptList, meta: { requiresAuth: true } },
        {
          path: '/prompts/:id',
          name: 'prompt-detail',
          component: PromptDetail,
          meta: { requiresAuth: true },
        },
        {
          path: '/nivelamento',
          name: 'nivelamento',
          component: () => import('../modules/nivelamento/views/NivelamentoPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: '/conquistas',
          name: 'conquistas',
          component: () => import('../modules/conquistas/views/ConquistasPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: '/pronuncia',
          name: 'pronuncia',
          component: () => import('../modules/pronuncia/views/PronunciaPratica.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: '/progresso',
          name: 'progresso',
          component: ProgressDashboard,
          meta: { requiresAuth: true },
        },
        {
          path: '/preferencias',
          name: 'preferencias',
          component: UserPreferences,
          meta: { requiresAuth: true },
        },
        {
          path: '/prompts/:promptId/traducoes',
          name: 'traducoes-prompt',
          component: TraducoesPrompt,
          meta: { requiresAuth: true },
        },
        {
          path: '/prompts/:promptId/imagens',
          name: 'imagens-prompt',
          component: ImagensPrompt,
          meta: { requiresAuth: true },
        },
        {
          path: '/prompts/:promptId/frases',
          name: 'frases-prompt',
          component: FrasesPrompt,
          meta: { requiresAuth: true },
        },
        // Rota Admin com permissao
        {
          path: '/admin',
          name: 'admin',
          component: AdminPanel,
          meta: {
            requiresAuth: true,
            requiresAdmin: true,
            permission: 'admin.access',
          },
        },
      ],
    },
    // Qualquer outro endereço
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundPage.vue'),
      meta: { requiresAuth: false },
    },
  ],
})

// Guarda de rota: usa o store de auth (sessão reidratada uma única vez via init())
router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  await authStore.init()

  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    return '/login'
  }

  // Rota admin exige role 'admin' vinda do servidor
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return '/dashboard'
  }

  // Verifica se rota requer permissao especifica
  if (to.meta.permission) {
    const permissionStore = usePermissionStore()
    await authStore.waitUserContext()

    if (permissionStore.userPermissions.length === 0) {
      await permissionStore.loadPermissions()
    }

    const requiredPermission = to.meta.permission as string
    if (!permissionStore.hasPermission(requiredPermission)) {
      return { path: '/dashboard', query: { error: 'no_permission' } }
    }
  }

  if ((to.path === '/login' || to.path === '/register' || to.path === '/') && isAuthenticated) {
    return '/dashboard'
  }

  return true
})

export default router
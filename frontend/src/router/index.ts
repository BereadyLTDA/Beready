import { createRouter, createWebHistory } from 'vue-router'

// Todas as views agora com lazy loading
const Home = () => import('../views/_global/Home.vue')
const Dashboard = () => import('../views/_global/Dashboard.vue')

const Login = () => import('../modules/auth/views/Login.vue')
const Register = () => import('../modules/auth/views/Register.vue')
const Profile = () => import('../modules/auth/views/Profile.vue')
const ProfileEdit = () => import('../modules/auth/views/ProfileEdit.vue')
const ForgotPassword = () => import('../modules/auth/views/ForgotPassword.vue')
const ResetPassword = () => import('../modules/auth/views/ResetPassword.vue')

const Flashcards = () => import('../modules/flashcards/views/Flashcards.vue')
const FlashcardView = () => import('../modules/flashcards/views/FlashcardView.vue')
const FlashcardStudy = () => import('../modules/flashcards/views/FlashcardStudy.vue')

const Quizes = () => import('../modules/quizes/views/Quizes.vue')
const QuizView = () => import('../modules/quizes/views/QuizView.vue')
const QuizPlay = () => import('../modules/quizes/views/QuizPlay.vue')
const QuizAdd = () => import('../modules/quizes/views/QuizAdd.vue')
const QuizEdit = () => import('../modules/quizes/views/QuizEdit.vue')

const Prompts = () => import('../modules/prompts/views/Prompts.vue')
const PromptDetail = () => import('../modules/prompts/views/PromptDetail.vue')
const Tags = () => import('../modules/tags/views/Tags.vue')
const ProgressoDashboard = () => import('../modules/progresso/views/ProgressoDashboard.vue')
const Preferencias = () => import('../modules/preferencias/views/Preferencias.vue')
const TraducoesPrompt = () => import('../modules/traducoes/views/TraducoesPrompt.vue')
const ImagensPrompt = () => import('../modules/imagens/views/ImagensPrompt.vue')
const FrasesPrompt = () => import('../modules/frases/views/FrasesPrompt.vue')

// Admin Panel
const AdminPanel = () => import('../modules/admin/views/AdminPanel.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: Home, meta: { requiresAuth: false } },
    { path: '/login', name: 'login', component: Login, meta: { requiresAuth: false } },
    { path: '/register', name: 'register', component: Register, meta: { requiresAuth: false } },
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/profile', name: 'profile', component: Profile, meta: { requiresAuth: true } },
    {
      path: '/profile/edit',
      name: 'profile-edit',
      component: ProfileEdit,
      meta: { requiresAuth: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPassword,
      meta: { requiresAuth: false },
    },
    {
      path: '/reset-password/:token',
      name: 'reset-password',
      component: ResetPassword,
      meta: { requiresAuth: false },
    },
    {
      path: '/flashcards',
      name: 'flashcards',
      component: Flashcards,
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
    { path: '/quizes', name: 'quizes', component: Quizes, meta: { requiresAuth: true } },
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
    { path: '/tags', name: 'tags', component: Tags, meta: { requiresAuth: true } },
    { path: '/prompts', name: 'prompts', component: Prompts, meta: { requiresAuth: true } },
    {
      path: '/prompts/:id',
      name: 'prompt-detail',
      component: PromptDetail,
      meta: { requiresAuth: true },
    },
    {
      path: '/progresso',
      name: 'progresso',
      component: ProgressoDashboard,
      meta: { requiresAuth: true },
    },
    {
      path: '/preferencias',
      name: 'preferencias',
      component: Preferencias,
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
    // Rota Admin
    {
      path: '/admin',
      name: 'admin',
      component: AdminPanel,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
  ],
})

// Guarda de rota atualizada com verificação de admin
router.beforeEach((to, from) => {
  const token = localStorage.getItem('access_token')
  const userData = localStorage.getItem('user')

  let isAuthenticated = false
  let isAdmin = false

  if (token && userData) {
    try {
      const user = JSON.parse(userData)
      isAuthenticated = true
      isAdmin = user.role === 'admin'
    } catch (e) {
      isAuthenticated = false
    }
  }

  // Verifica se rota requer admin
  if (to.meta.requiresAdmin && !isAdmin) {
    return '/dashboard'
  }

  if (to.meta.requiresAuth && !isAuthenticated) {
    return '/login'
  }

  if ((to.path === '/login' || to.path === '/register' || to.path === '/') && isAuthenticated) {
    return '/dashboard'
  }

  return true
})

export default router

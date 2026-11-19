import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAlert } from '@/shared/composables/useAlert'
import { useFocusTrap } from '@/shared/composables/useFocusTrap'
import type { IconName } from '@/shared/components/ui'
import { useAuthStore } from '@/stores/auth'

export interface NavItem {
  /** Chave i18n do rótulo */
  name: string
  path: string
  icon: IconName
}

// Itens do menu principal (ordem de exibição)
export const menuItems: NavItem[] = [
  { name: 'common.dashboard', path: '/dashboard', icon: 'home' },
  { name: 'common.perfil', path: '/profile', icon: 'user' },
  { name: 'common.planos', path: '/planos', icon: 'shield-check' },
  { name: 'common.flashcards', path: '/flashcards', icon: 'document' },
  { name: 'common.quizes', path: '/quizes', icon: 'clipboard' },
  { name: 'common.prompts', path: '/prompts', icon: 'chat' },
  { name: 'pronuncia.menu', path: '/pronuncia', icon: 'microphone' },
  { name: 'common.tags', path: '/tags', icon: 'tag' },
  { name: 'common.progresso', path: '/progresso', icon: 'chart-bar' },
  { name: 'common.preferencias', path: '/preferencias', icon: 'cog' },
]

// Item extra para role admin: abre o Painel Administrativo (as abas ficam dentro dele)
const adminItem: NavItem = { name: 'admin.badge', path: '/admin', icon: 'shield-check' }

// A partir deste breakpoint o menu fica inline (ver navbar.css); abaixo, drawer
const DESKTOP_QUERY = '(min-width: 1280px)'

export function useNavbarLogic() {
  const authStore = useAuthStore()
  const route = useRoute()
  const { success } = useAlert()
  const { t } = useI18n()

  const user = computed(() => authStore.user)
  const userName = computed(() => user.value?.nome || t('common.usuario'))
  const userEmail = computed(() => user.value?.email || '')
  const userInitial = computed(() => (user.value?.nome || '').charAt(0).toUpperCase() || 'U')

  // Ativo também nas sub-rotas (/flashcards/12, /prompts/3/traducoes...)
  const isActive = (path: string): boolean =>
    route.path === path || route.path.startsWith(`${path}/`)

  const visibleMenuItems = computed(() => (authStore.isAdmin ? [...menuItems, adminItem] : menuItems))

  // Drawer (menu mobile/tablet)
  const isMenuOpen = ref(false)
  const drawerRef = ref<HTMLElement | null>(null)

  const openMenu = () => {
    isMenuOpen.value = true
  }
  const closeMenu = () => {
    isMenuOpen.value = false
  }
  const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value
  }

  // Foco preso no drawer, Esc fecha, rolagem do body bloqueada, foco volta ao botão
  useFocusTrap(drawerRef, isMenuOpen, { onEscape: closeMenu })

  // Fecha ao navegar
  watch(() => route.fullPath, closeMenu)

  // Fecha se a janela crescer até o layout desktop
  let desktopMql: MediaQueryList | null = null
  const onDesktopChange = (event: MediaQueryListEvent) => {
    if (event.matches) closeMenu()
  }
  onMounted(() => {
    desktopMql = window.matchMedia(DESKTOP_QUERY)
    desktopMql.addEventListener('change', onDesktopChange)
  })
  onBeforeUnmount(() => {
    desktopMql?.removeEventListener('change', onDesktopChange)
  })

  const handleLogout = async () => {
    closeMenu()
    await authStore.logout()
    success(t('success.logout'))
  }

  return {
    user,
    userName,
    userEmail,
    userInitial,
    menuItems: visibleMenuItems,
    isActive,
    isMenuOpen,
    drawerRef,
    openMenu,
    closeMenu,
    toggleMenu,
    handleLogout,
  }
}

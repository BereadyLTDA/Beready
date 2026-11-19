import plugin from 'tailwindcss/plugin'

/**
 * Tailwind ligado ao design system (src/styles/tokens.css).
 *
 * - O tema SUBSTITUI o padrão do Tailwind: só existem cores, espaçamentos, raios,
 *   sombras e tipografia que apontam para os tokens. Assim nenhuma classe foge dos
 *   temas (claro, escuro, daltônico e escuro + daltônico trocam só as variáveis).
 *   Ex.: `bg-surface text-muted p-4 rounded-lg shadow-sm md:grid-cols-2`.
 * - Como as cores são var(--color-*), modificadores de opacidade (`bg-primary/50`)
 *   não funcionam; use os tokens `-soft` para fundos suaves.
 * - Preflight desligado: o reset/base próprio fica em src/styles/tailwind.css (@layer base).
 * - Variantes: `dark:` (classe .dark-mode), `daltonico:` (classe .daltonico-mode),
 *   `rtl:`/`ltr:` (atributo dir). Com tokens, raramente são necessárias.
 * - Breakpoints iguais aos do guia (docs/design-system.md): 480/768/1024/1280/1536.
 */
const token = (name) => `var(--${name})`

const statusColor = (name, { hover = false } = {}) => ({
  DEFAULT: token(`color-${name}`),
  contrast: token(`color-${name}-contrast`),
  soft: token(`color-${name}-soft`),
  ...(hover ? { hover: token(`color-${name}-hover`) } : {}),
})

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  darkMode: ['selector', '.dark-mode'],
  corePlugins: {
    preflight: false,
    // Largura de página é responsabilidade do <PageContainer>
    container: false,
  },
  theme: {
    screens: {
      sm: '480px',
      md: '768px',
      lg: '1024px',
      xl: '1280px',
      '2xl': '1536px',
    },
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      inherit: 'inherit',
      bg: token('color-bg'),
      surface: {
        DEFAULT: token('color-surface'),
        muted: token('color-surface-muted'),
        hover: token('color-surface-hover'),
      },
      border: {
        DEFAULT: token('color-border'),
        strong: token('color-border-strong'),
      },
      text: {
        DEFAULT: token('color-text'),
        muted: token('color-text-muted'),
        subtle: token('color-text-subtle'),
        inverse: token('color-text-inverse'),
      },
      primary: {
        DEFAULT: token('color-primary'),
        hover: token('color-primary-hover'),
        active: token('color-primary-active'),
        contrast: token('color-primary-contrast'),
        soft: token('color-primary-soft'),
        'soft-text': token('color-primary-soft-text'),
      },
      success: statusColor('success', { hover: true }),
      warning: statusColor('warning'),
      danger: statusColor('danger', { hover: true }),
      info: statusColor('info'),
      focus: token('color-focus-ring'),
      overlay: token('color-overlay'),
      skeleton: token('color-skeleton'),
      hero: {
        from: token('color-hero-from'),
        to: token('color-hero-to'),
        text: token('color-hero-text'),
        'text-muted': token('color-hero-text-muted'),
      },
    },
    spacing: {
      0: token('space-0'),
      px: '1px',
      1: token('space-1'),
      2: token('space-2'),
      3: token('space-3'),
      4: token('space-4'),
      5: token('space-5'),
      6: token('space-6'),
      8: token('space-8'),
      10: token('space-10'),
      12: token('space-12'),
      16: token('space-16'),
    },
    borderRadius: {
      none: '0',
      sm: token('radius-sm'),
      DEFAULT: token('radius-md'),
      md: token('radius-md'),
      lg: token('radius-lg'),
      xl: token('radius-xl'),
      '2xl': token('radius-2xl'),
      full: token('radius-full'),
    },
    boxShadow: {
      none: 'none',
      sm: token('shadow-sm'),
      DEFAULT: token('shadow-md'),
      md: token('shadow-md'),
      lg: token('shadow-lg'),
      xl: token('shadow-xl'),
    },
    fontFamily: {
      sans: token('font-family-base'),
      mono: token('font-family-mono'),
    },
    fontSize: {
      xs: token('font-size-xs'),
      sm: token('font-size-sm'),
      base: token('font-size-base'),
      lg: token('font-size-lg'),
      xl: token('font-size-xl'),
      '2xl': token('font-size-2xl'),
      '3xl': token('font-size-3xl'),
      '4xl': token('font-size-4xl'),
      display: token('font-size-display'),
    },
    fontWeight: {
      regular: token('font-weight-regular'),
      medium: token('font-weight-medium'),
      semibold: token('font-weight-semibold'),
      bold: token('font-weight-bold'),
    },
    lineHeight: {
      none: '1',
      tight: token('line-height-tight'),
      base: token('line-height-base'),
      relaxed: token('line-height-relaxed'),
    },
    zIndex: {
      auto: 'auto',
      base: token('z-base'),
      dropdown: token('z-dropdown'),
      sticky: token('z-sticky'),
      drawer: token('z-drawer'),
      modal: token('z-modal'),
      toast: token('z-toast'),
      tooltip: token('z-tooltip'),
    },
    transitionDuration: {
      fast: token('duration-fast'),
      DEFAULT: token('duration-base'),
      base: token('duration-base'),
      slow: token('duration-slow'),
    },
    extend: {
      /*
       * Espaçamentos/tamanhos extras. Entram em p-/m-/gap-/w-/h-/size-/min-*-/max-*-/
       * inset-/translate-/basis- (o Tailwind deriva essas escalas de `spacing`).
       */
      spacing: {
        0.5: token('space-0-5'),
        1.5: token('space-1-5'),
        11: token('space-11'),
        14: token('space-14'),
        48: token('space-48'),
        64: token('space-64'),
        72: token('space-72'),
        nudge: token('space-nudge'),
        'nudge-em': token('space-nudge-em'),
        gutter: token('page-gutter'),
        // Controles (altura = largura de botão só-ícone)
        control: token('control-height-md'),
        'control-sm': token('control-height-sm'),
        'control-lg': token('control-height-lg'),
        // Botão interno do campo (mostrar senha) e recuos de ícone/chevron dentro do campo
        'control-inner': `calc(${token('control-height-md')} - ${token('space-2')})`,
        'control-icon': `calc(${token('space-3')} * 2 + ${token('space-5')})`,
        'control-chevron': `calc(${token('space-3')} * 2 + ${token('space-4')})`,
        // Recuo da dica do checkbox (caixa + gap)
        'check-indent': `calc(${token('space-5')} + ${token('space-3')})`,
        // Ícones relativos à fonte
        em: token('icon-size-em'),
        'em-md': token('icon-size-em-md'),
        'em-lg': token('icon-size-em-lg'),
        check: token('icon-size-check'),
        // Telas: avatares, medalhões, mínimos de grade fluida e bases flex (n × 0.25rem)
        4.5: token('space-4-5'),
        7: token('space-7'),
        9: token('space-9'),
        13: token('space-13'),
        18: token('space-18'),
        20: token('space-20'),
        28: token('space-28'),
        34: token('space-34'),
        36: token('space-36'),
        40: token('space-40'),
        44: token('space-44'),
        56: token('space-56'),
        60: token('space-60'),
        68: token('space-68'),
        70: token('space-70'),
        80: token('space-80'),
        88: token('space-88'),
        96: token('space-96'),
        112: token('space-112'),
        navbar: token('navbar-height'),
        drawer: token('drawer-width'),
        // Respiro vertical fluido das seções públicas (`py-fluid-hero`)
        'fluid-hero': token('space-fluid-hero'),
        'fluid-section': token('space-fluid-section'),
      },
      colors: {
        // Cor da tag vinda do dado (inline `style="--tag-color: …"`); sem cor = borda forte
        tag: `var(--tag-color, ${token('color-border-strong')})`,
      },
      borderRadius: {
        inherit: 'inherit',
      },
      aspectRatio: {
        photo: token('aspect-photo'),
      },
      maxWidth: {
        'container-sm': token('container-sm'),
        'container-md': token('container-md'),
        'container-lg': token('container-lg'),
        'container-xl': token('container-xl'),
        // PageContainer: largura do conteúdo + gutter dos dois lados
        'page-sm': `calc(${token('container-sm')} + ${token('page-gutter')} * 2)`,
        'page-md': `calc(${token('container-md')} + ${token('page-gutter')} * 2)`,
        'page-lg': `calc(${token('container-lg')} + ${token('page-gutter')} * 2)`,
        'page-xl': `calc(${token('container-xl')} + ${token('page-gutter')} * 2)`,
        'modal-sm': token('modal-width-sm'),
        'modal-md': token('modal-width-md'),
        'modal-lg': token('modal-width-lg'),
        'modal-xl': token('modal-width-xl'),
        measure: token('measure-sm'),
      },
      maxHeight: {
        modal: `calc(100dvh - ${token('space-8')})`,
      },
      minHeight: {
        control: token('control-height-md'),
        'control-sm': token('control-height-sm'),
        'control-lg': token('control-height-lg'),
        textarea: `calc(${token('control-height-md')} * 2)`,
        flashcard: token('flashcard-min-height'),
      },
      minWidth: {
        // BaseTable: largura mínima opcional (prop minWidth → --ui-table-min)
        table: 'var(--ui-table-min, 100%)',
      },
      width: {
        toast: `min(${token('toast-width')}, calc(100vw - ${token('space-8')}))`,
      },
      height: {
        'toast-progress': token('toast-progress-height'),
      },
      fontSize: {
        'icon-lg': token('font-size-icon-lg'),
        'icon-xl': token('font-size-icon-xl'),
        // Tipografia fluida: clamp(menor, preferido, maior)
        'fluid-lg-2xl': token('font-size-fluid-lg-2xl'),
        'fluid-2xl-3xl': token('font-size-fluid-2xl-3xl'),
        'fluid-2xl-4xl': token('font-size-fluid-2xl-4xl'),
        'fluid-3xl-4xl': token('font-size-fluid-3xl-4xl'),
      },
      lineHeight: {
        inherit: 'inherit',
      },
      letterSpacing: {
        label: token('letter-spacing-label'),
      },
      opacity: {
        disabled: token('opacity-disabled'),
      },
      zIndex: {
        raised: token('z-raised'),
      },
      borderWidth: {
        DEFAULT: token('border-width'),
        3: '3px',
      },
      // `ring` = anel de --focus-ring-width. Sempre com cor explícita (`ring-primary-soft`,
      // `ring-focus`…): ringColor.DEFAULT com var() é ignorado pelo Tailwind.
      ringWidth: {
        DEFAULT: token('focus-ring-width'),
      },
      boxShadow: {
        // Aba ativa: barra inferior na cor da marca + sombra leve
        'tab-active': `inset 0 calc(${token('focus-ring-width')} * -1) 0 ${token('color-primary')}, ${token('shadow-sm')}`,
        // Navbar: link ativo sublinhado (menu inline) / barra no início (gaveta; espelhada em RTL)
        'nav-active': `inset 0 calc(${token('space-0-5')} * -1) 0 ${token('color-primary')}`,
        'drawer-active': `inset 3px 0 0 ${token('color-primary')}`,
        'drawer-active-rtl': `inset -3px 0 0 ${token('color-primary')}`,
      },
      scale: {
        enter: token('scale-enter'),
        'enter-sm': token('scale-enter-sm'),
      },
      content: {
        // BaseTable empilhado: rótulo da coluna antes do valor (`before:content-label`)
        label: 'attr(data-label)',
        // Pseudo-elemento decorativo vazio (`before:content-empty`) e dois-pontos após rótulo
        empty: '""',
        colon: '":"',
      },
      transitionDuration: {
        // Virada do flashcard (2 × slow; zera junto com --duration-slow em movimento reduzido)
        flip: `calc(${token('duration-slow')} * 2)`,
      },
      // `transition`/`transition-*` usam por padrão --duration-base e --easing-standard
      transitionTimingFunction: {
        DEFAULT: token('easing-standard'),
        standard: token('easing-standard'),
        emphasized: token('easing-emphasized'),
      },
      transitionProperty: {
        control: 'color, background-color, border-color, box-shadow',
        card: 'box-shadow, border-color, transform',
        enter: 'opacity, transform',
        size: 'inline-size',
        // Painel que aparece (esmaece + desliza; visibility tira da ordem de Tab)
        reveal: 'opacity, transform, visibility',
      },
      keyframes: {
        'toast-progress': {
          from: { inlineSize: '100%' },
          to: { inlineSize: '0' },
        },
      },
      animation: {
        spinner: `spin ${token('duration-spin')} linear infinite`,
        'spinner-fast': `spin ${token('duration-spin-fast')} linear infinite`,
        // a duração vem do inline style (duração do toast)
        'toast-progress': 'toast-progress linear forwards',
      },
      aria: {
        invalid: 'invalid="true"',
      },
      backgroundImage: {
        brand: token('gradient-brand'),
      },
    },
  },
  plugins: [
    plugin(({ addVariant, addUtilities, addComponents, matchUtilities, theme }) => {
      addVariant('daltonico', ':is(.daltonico-mode &)')

      /*
       * <Transition>/<TransitionGroup> do Vue: os objetos de ./src/shared/components/ui/transitions.ts
       * colocam `ui-t-active` (enter/leave-active) e `ui-t-from` (enter-from/leave-to) na raiz.
       * Estas variantes estilizam a própria raiz ou um descendente (ex.: o diálogo dentro do overlay).
       */
      addVariant('t-active', ['&.ui-t-active', '.ui-t-active &'])
      addVariant('t-from', ['&.ui-t-from', '.ui-t-from &'])

      // Estado de um `peer` alcançando o descendente de um irmão (checkbox: input + label > caixa)
      addVariant('peer-checked-deep', ':merge(.peer):checked ~ * &')
      addVariant('peer-focus-visible-deep', ':merge(.peer):focus-visible ~ * &')

      // Hover só em dispositivos com ponteiro que passa por cima (evita "hover preso" no toque)
      addVariant('can-hover', '@media (hover: hover) { &:hover }')

      // Filho único que não é botão/link (ex.: texto no rodapé do BaseCard ocupa a linha)
      addVariant('only-text', '& > :only-child:not(button):not(a)')

      addUtilities({
        // Anel de foco padrão (igual ao :focus-visible global do reset base (tailwind.css))
        '.focus-ring': {
          outline: `${token('focus-ring-width')} solid ${token('color-focus-ring')}`,
          'outline-offset': token('focus-ring-offset'),
        },
        // Anel por dentro: para elementos em contêiner com overflow (abas, tabela)
        '.focus-ring-inset': {
          outline: `${token('focus-ring-width')} solid ${token('color-focus-ring')}`,
          'outline-offset': `calc(${token('focus-ring-width')} * -1)`,
        },
        '.wrap-anywhere': { 'overflow-wrap': 'anywhere' },
        '.scrollbar-thin': { 'scrollbar-width': 'thin' },
        '.scrollbar-none': { 'scrollbar-width': 'none', '&::-webkit-scrollbar': { display: 'none' } },
        // Esconde visualmente mantendo na árvore de acessibilidade (só o recorte, sem mexer em tamanho/posição como `sr-only`)
        '.clip-hidden': { clip: 'rect(0, 0, 0, 0)' },
        '.clip-auto': { clip: 'auto' },
        // Altura mínima da viewport: 100vh como fallback para navegadores sem dvh
        '.min-h-viewport': { minHeight: ['100vh', '100dvh'] },
        // Virada 3D (flashcard): perspectiva no pai, preserve-3d no miolo, faces sem verso
        '.perspective-card': { perspective: token('perspective-card') },
        '.preserve-3d': { transformStyle: 'preserve-3d' },
        // Sem -webkit-: o autoprefixer o remove, pois os navegadores do browserslist
        // (Safari >= 15.4) já suportam backface-visibility sem prefixo
        '.backface-hidden': { backfaceVisibility: 'hidden' },
        '.rotate-y-180': { transform: 'rotateY(180deg)' },
        // `outline-none` do Tailwind é contorno transparente; este é `outline: none` de fato
        '.outline-hidden': { outline: 'none' },
      })

      /*
       * Na camada `components` (antes dos utilitários) para que `text-sm`/`font-medium`
       * no mesmo elemento continuem valendo: zera a fonte de <button> nativo (herda do pai).
       */
      addComponents({
        '.font-inherit': { font: 'inherit' },
      })

      /*
       * Grades fluidas sem media query: `grid-cols-fit` (auto-fit) e `grid-cols-fill` (auto-fill).
       * Mínimo da coluna: sufixo da escala de espaçamento (`grid-cols-fit-48` = 12rem) ou,
       * sem sufixo, `--u-min` (padrão --space-72 = 18rem): `class="grid grid-cols-fill" style="--u-min: 16rem"`.
       */
      const fluidValues = { DEFAULT: `var(--u-min, ${token('space-72')})`, ...theme('spacing') }
      matchUtilities(
        {
          'grid-cols-fit': (value) => ({
            gridTemplateColumns: `repeat(auto-fit, minmax(min(100%, ${value}), 1fr))`,
          }),
          'grid-cols-fill': (value) => ({
            gridTemplateColumns: `repeat(auto-fill, minmax(min(100%, ${value}), 1fr))`,
          }),
        },
        { values: fluidValues },
      )
    }),
  ],
}

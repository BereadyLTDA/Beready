import { existsSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import type { Connect, Plugin } from 'vite'

// Sem isto, o fallback da SPA responde 200 com o index.html para /.well-known/* e para .txt/.json
// inexistentes, e robôs e o Lighthouse leem HTML onde esperavam outro formato. O nginx de produção
// faz o mesmo (docker/nginx.conf). Arquivos que existem em public/ continuam sendo servidos.
const naoEncontradoParaArquivos = (): Plugin => {
  const middleware =
    (pasta: string): Connect.NextHandleFunction =>
    (req, res, next) => {
      const caminho = decodeURIComponent((req.url ?? '').split('?')[0] ?? '')
      // Só a raiz do site: módulos do Vite como /src/locales/x.json não passam por aqui
      const ehArquivoDeRobo =
        caminho.startsWith('/.well-known/') || /^\/[^/]+\.(txt|json|xml)$/.test(caminho)
      if (ehArquivoDeRobo && !existsSync(join(pasta, caminho))) {
        res.statusCode = 404
        res.end('Not found')
        return
      }
      next()
    }
  return {
    name: 'beready-404-arquivos',
    configureServer: (server) => {
      server.middlewares.use(middleware(server.config.publicDir))
    },
    configurePreviewServer: (server) => {
      server.middlewares.use(middleware(server.config.build.outDir))
    },
  }
}

export default defineConfig({
  plugins: [
    naoEncontradoParaArquivos(),
    vue({
      template: {
        compilerOptions: {
          // Permite nomes de componentes com uma única palavra
          isCustomElement: (tag) => false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('vue')) return 'vendor-vue'
            return 'vendor'
          }
        },
      },
    },
    chunkSizeWarningLimit: 600,
  },
  server: {
    watch: { usePolling: true },
  },
  test: {
    // Só os testes unitários: os *.spec.ts de e2e/ são do Playwright (npm run test:e2e)
    include: ['src/**/*.spec.ts'],
    environment: 'node',
  },
})

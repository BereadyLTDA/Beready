import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
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

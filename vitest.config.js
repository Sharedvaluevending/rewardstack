import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [
    vue(),
  ],
  test: {
    environment: 'jsdom',
    globals: true, // Enable global test functions (describe, it, expect)
    setupFiles: ['./resources/js/test/setup.js'],
    include: ['resources/js/**/*.test.js'],
    coverage: {
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/**',
        'resources/js/test/**',
        '**/*.test.js',
        '**/*.test.vue'
      ]
    }
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
      '@components': resolve(__dirname, 'resources/js/Components'),
      '@pages': resolve(__dirname, 'resources/js/Pages'),
      '@utils': resolve(__dirname, 'resources/js/utils'),
      '@test': resolve(__dirname, 'resources/js/test'),
    }
  }
})
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  build: {
    lib: {
      entry: resolve(__dirname, 'src/index.js'),
      name: 'PublixxCatalog',
      formats: ['umd', 'es'],
      fileName: (format) => `catalog-embed.${format}.js`,
    },
    rollupOptions: {
      // Bundle everything — no external dependencies
      external: [],
    },
    cssCodeSplit: false,
    minify: 'esbuild',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
})

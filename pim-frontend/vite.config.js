import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  base: process.env.VITE_BASE_PATH || '/',
  plugins: [
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    assetsDir: 'pim-assets',
    // Gzip-Groessenberechnung nach dem Bundling ist rein kosmetisch (nur fuer die
    // Terminal-Ausgabe), belastet bei den grossen Chunks dieses Projekts (siehe
    // "chunks larger than 500 kB"-Warnung) aber den Speicher zusaetzlich und hat
    // bei Deploys mit wenig RAM zu OOM-Abstuerzen des Build-Prozesses gefuehrt.
    reportCompressedSize: false,
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia', 'axios'],
          charts: ['chart.js', 'vue-chartjs'],
          editor: ['@monaco-editor/loader'],
          maps: ['leaflet'],
        },
      },
    },
  },
})

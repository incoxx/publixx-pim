import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// Determine build target from VITE_BUILD_TARGET env var
const buildTarget = process.env.VITE_BUILD_TARGET || 'online'
const isDebugBuild = process.env.VITE_DEBUG_BUILD === '1'

const onlineConfig = {
  entry: resolve(__dirname, 'src/index.js'),
  name: 'PublixxCatalog',
  formats: ['umd', 'es'],
  fileName: (format) => `catalog-embed.${format}.js`,
}

const offlineConfig = {
  entry: resolve(__dirname, 'src/offline-init.js'),
  name: 'PublixxCatalogOffline',
  formats: ['umd'],
  fileName: () => 'catalog-offline.umd.js',
}

export default defineConfig({
  plugins: [vue()],
  build: {
    lib: buildTarget === 'offline' ? offlineConfig : onlineConfig,
    rollupOptions: {
      // Bundle everything — no external dependencies
      external: [],
      output: {
        exports: 'named',
      },
    },
    cssCodeSplit: false,
    minify: isDebugBuild ? false : 'esbuild',
    sourcemap: isDebugBuild,
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
})

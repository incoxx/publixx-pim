import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  build: {
    lib: {
      entry: resolve(__dirname, 'src/index.js'),
      name: 'PortalEmbed',
      formats: ['umd', 'es'],
      fileName: (format) => `portal-embed.${format}.js`,
    },
    rollupOptions: {
      external: [],
      output: {
        exports: 'named',
      },
    },
    cssCodeSplit: false,
    minify: 'esbuild',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
})

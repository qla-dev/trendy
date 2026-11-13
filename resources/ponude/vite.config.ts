import react from '@vitejs/plugin-react'
import path from 'path'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  base: '/ponude/',
  build: {
    outDir: path.resolve(__dirname, '../../public/ponude'),
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api/cnc': {
        target: 'https://enalog.app',
        changeOrigin: true,
      },
    },
  },
})

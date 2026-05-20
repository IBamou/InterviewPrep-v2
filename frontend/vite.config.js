import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, import.meta.dirname, '')

  return {
    plugins: [react()],
    server: {
      port: parseInt(env.VITE_PORT) || 5199,
      proxy: {
        '/api': env.VITE_API_URL || 'http://backend.test',
      },
    },
  }
})

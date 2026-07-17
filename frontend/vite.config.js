import { fileURLToPath } from 'node:url'
import path from 'node:path'
import process from 'node:process'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

// https://vite.dev/config/
export default defineConfig(({ command, mode }) => {
  if (command === 'build') {
    const env = { ...loadEnv(mode, process.cwd(), ''), ...process.env }
    const missing = ['VITE_REVERB_APP_KEY', 'VITE_REVERB_URL'].filter((key) => !env[key])

    if (missing.length > 0) {
      throw new Error(`Faltan variables obligatorias para Reverb: ${missing.join(', ')}`)
    }
  }

  return {
    plugins: [react(), tailwindcss()],
    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      allowedHosts: [
        'sipregamcdev.cochabamba.bo',
      ],
      watch: {
        usePolling: true,
      },
    },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
  }
})


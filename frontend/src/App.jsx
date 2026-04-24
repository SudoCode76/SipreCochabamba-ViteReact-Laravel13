import { useEffect, useMemo, useState } from 'react'
import { ArrowRight, Boxes, Database, Server, Sparkles } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

const apiBaseUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

const stack = [
  'Laravel 13',
  'PostgreSQL',
  'React',
  'Vite 8.0.10',
  'shadcn/ui',
  'Docker Compose',
]

const checkpoints = [
  {
    title: 'Backend aislado',
    description: 'API Laravel lista para conectarse al dump PostgreSQL restaurado desde Docker.',
    icon: Server,
  },
  {
    title: 'Base restaurable',
    description: 'El backup `sipre-202604201330_pg_backup.dmp` se carga durante la inicializacion de Postgres.',
    icon: Database,
  },
  {
    title: 'Frontend desacoplado',
    description: 'React con Vite y componentes shadcn para arrancar una interfaz moderna desde cero.',
    icon: Sparkles,
  },
]

function App() {
  const [status, setStatus] = useState({ state: 'loading', label: 'Verificando API...' })

  useEffect(() => {
    let active = true

    fetch(`${apiBaseUrl}/health`)
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`)
        }

        return response.json()
      })
      .then((data) => {
        if (!active) {
          return
        }

        setStatus({
          state: 'ready',
          label: `API lista · DB ${data.database ?? 'pgsql'}`,
        })
      })
      .catch(() => {
        if (!active) {
          return
        }

        setStatus({
          state: 'offline',
          label: 'API aun no disponible',
        })
      })

    return () => {
      active = false
    }
  }, [])

  const statusTone = useMemo(() => {
    if (status.state === 'ready') {
      return 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30'
    }

    if (status.state === 'offline') {
      return 'bg-amber-500/15 text-amber-700 border-amber-500/30'
    }

    return 'bg-slate-500/10 text-slate-700 border-slate-500/20'
  }, [status.state])

  return (
    <main className="min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.16),_transparent_28%),linear-gradient(180deg,_#fffaf1_0%,_#f4efe6_42%,_#ebe5db_100%)] text-slate-900">
      <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 sm:px-10 lg:px-12">
        <header className="animate-in fade-in slide-in-from-top-6 flex items-center justify-between gap-4 duration-700">
          <div>
            <p className="text-sm uppercase tracking-[0.3em] text-slate-500">Sipre Cochabamba</p>
            <h1 className="mt-3 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
              Base Dockerizada para una nueva plataforma con Laravel 13 y React.
            </h1>
          </div>
          <Badge className={`hidden rounded-full border px-4 py-1 text-sm sm:inline-flex ${statusTone}`}>
            {status.label}
          </Badge>
        </header>

        <section className="grid flex-1 gap-8 py-10 lg:grid-cols-[1.25fr_0.75fr] lg:items-center lg:py-14">
          <div className="animate-in fade-in slide-in-from-left-6 space-y-8 duration-700">
            <div className="max-w-2xl space-y-5 text-lg leading-8 text-slate-700">
              <p>
                El proyecto queda separado en <span className="font-semibold text-slate-950">`backend`</span> y{' '}
                <span className="font-semibold text-slate-950">`frontend`</span>, con PostgreSQL restaurado desde el dump existente
                y una interfaz inicial lista para evolucionar a modulos reales.
              </p>
              <div className="flex flex-wrap gap-2">
                {stack.map((item) => (
                  <Badge key={item} variant="outline" className="rounded-full border-slate-300 bg-white/70 px-3 py-1 text-slate-700">
                    {item}
                  </Badge>
                ))}
              </div>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
              <Button className="h-11 rounded-full bg-slate-950 px-5 text-sm font-medium text-white hover:bg-slate-800" asChild>
                <a href="https://laravel.com/docs/13.x" target="_blank" rel="noreferrer">
                  Revisar backend
                  <ArrowRight className="size-4" />
                </a>
              </Button>
              <Button variant="outline" className="h-11 rounded-full border-slate-300 bg-white/80 px-5 text-sm" asChild>
                <a href="https://ui.shadcn.com" target="_blank" rel="noreferrer">
                  Explorar shadcn/ui
                </a>
              </Button>
            </div>
          </div>

          <Card className="animate-in fade-in slide-in-from-right-6 border-white/70 bg-white/70 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.45)] backdrop-blur duration-700">
            <CardHeader>
              <CardTitle className="text-2xl">Punto de arranque</CardTitle>
              <CardDescription>
                Un tablero inicial para validar que la arquitectura ya esta viva antes de construir los modulos del sistema.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
              {checkpoints.map(({ title, description, icon: Icon }) => (
                <div key={title} className="rounded-2xl border border-slate-200/80 bg-white/80 p-4">
                  <div className="mb-3 flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                      <Icon className="size-5" />
                    </span>
                    <h2 className="text-lg font-semibold text-slate-950">{title}</h2>
                  </div>
                  <p className="text-sm leading-6 text-slate-600">{description}</p>
                </div>
              ))}

              <Separator className="bg-slate-200" />

              <div className="flex items-center gap-3 rounded-2xl bg-slate-950 px-4 py-4 text-slate-50">
                <Boxes className="size-5 shrink-0 text-amber-300" />
                <p className="text-sm leading-6">
                  Ejecuta <span className="font-semibold">`docker compose up --build`</span> y abre el frontend para continuar con el desarrollo.
                </p>
              </div>
            </CardContent>
          </Card>
        </section>
      </div>
    </main>
  )
}

export default App

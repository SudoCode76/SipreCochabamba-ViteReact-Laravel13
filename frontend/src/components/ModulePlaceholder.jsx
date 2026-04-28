import { ArrowUpRight, Clock3, Sparkles } from "lucide-react";
import { Link } from "react-router-dom";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";

const accentStyles = {
  slate: "bg-slate-900 text-white",
  emerald: "bg-emerald-600 text-white",
  amber: "bg-amber-500 text-black",
  sky: "bg-sky-600 text-white",
  violet: "bg-violet-600 text-white",
  rose: "bg-rose-600 text-white",
};

export default function ModulePlaceholder({
  title,
  description,
  accent = "slate",
  section,
}) {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex max-w-2xl flex-col gap-3">
          <Badge variant="outline" className="w-fit rounded-full border-border/70 bg-background/80 px-3 py-1 text-[11px] uppercase tracking-[0.24em] text-muted-foreground">
            {section}
          </Badge>
          <div className="flex flex-col gap-2">
            <h1 className="font-heading text-3xl font-semibold tracking-[-0.04em] text-foreground sm:text-4xl">
              {title}
            </h1>
            <p className="max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
              {description}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3 rounded-full border border-border/70 bg-background/85 px-4 py-2 shadow-[0_10px_30px_rgba(15,23,42,0.06)] backdrop-blur">
          <span className={`size-2.5 rounded-full ${accentStyles[accent] ?? accentStyles.slate}`} />
          <span className="text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
            En preparación
          </span>
        </div>
      </div>

      <Card className="border border-border/70 bg-white/85 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-3">
          <Badge variant="outline" className="w-fit rounded-full border-border/70 bg-muted/50 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-muted-foreground">
            Próximo módulo
          </Badge>
          <CardTitle className="text-2xl tracking-[-0.03em]">Interfaz base lista para crecer</CardTitle>
          <CardDescription className="max-w-2xl text-sm leading-6">
            Este espacio ya adopta el nuevo lenguaje visual y puede llenarse con tablas, formularios o flujos reales sin rehacer la estructura principal.
          </CardDescription>
        </CardHeader>

        <CardContent className="grid gap-4 lg:grid-cols-[1.4fr_0.9fr]">
          <div className="rounded-[28px] border border-border/70 bg-muted/30 p-5">
            <div className="flex items-center gap-3 text-sm font-medium text-foreground">
              <Sparkles className="size-4 text-muted-foreground" />
              Qué debería entrar aquí
            </div>
            <Separator className="my-4 bg-border/70" />
            <div className="grid gap-3 text-sm text-muted-foreground sm:grid-cols-2">
              <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                Vistas de listado con filtros sobrios y foco en lectura.
              </div>
              <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                Formularios con jerarquía clara y feedback directo.
              </div>
              <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                Estados, badges y acciones rápidas sin ruido visual.
              </div>
              <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                Paneles y detalles listos para enlazar con datos reales.
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-4 rounded-[28px] border border-dashed border-border bg-background/90 p-5">
            <div className="flex items-center gap-3 text-sm font-medium text-foreground">
              <Clock3 className="size-4 text-muted-foreground" />
              Estado actual
            </div>
            <p className="text-sm leading-6 text-muted-foreground">
              La navegación, contenedores y estética final ya están definidos. Solo falta conectar la funcionalidad específica de este módulo.
            </p>
            <div className="rounded-2xl border border-border/70 bg-muted/40 p-4 text-xs uppercase tracking-[0.2em] text-muted-foreground">
              Diseñado para expandirse con componentes shadcn
            </div>
          </div>
        </CardContent>

        <CardFooter className="flex flex-wrap justify-between gap-3 border-t border-border/70 bg-transparent">
          <p className="text-sm text-muted-foreground">
            Si quieres, este módulo puede ser el siguiente en pasar de placeholder a pantalla funcional.
          </p>
          <Button asChild variant="outline" className="rounded-full border-border/70 bg-background/80 px-4">
            <Link to="/dashboard">
              Volver al dashboard
              <ArrowUpRight data-icon="inline-end" />
            </Link>
          </Button>
        </CardFooter>
      </Card>
    </div>
  );
}

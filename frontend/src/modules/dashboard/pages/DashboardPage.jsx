import { ChevronLeft, ChevronRight, FileText, Package, Search, SlidersHorizontal } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";

const supplyRows = [
  {
    id: 1,
    description: "Abono foliar",
    price: "85.00",
    unit: "litro",
    abbreviation: "L",
    type: "MATERIAL",
    quoteDate: "2020-11-11",
    status: "HABILITADO",
  },
  {
    id: 2,
    description: "Abono vegetal",
    price: "195.00",
    unit: "metro cúbico",
    abbreviation: "m³",
    type: "MATERIAL",
    quoteDate: "2025-06-05",
    status: "HABILITADO",
  },
  {
    id: 3,
    description: "Abono vegetal preparado",
    price: "150.00",
    unit: "metro cúbico",
    abbreviation: "m³",
    type: "MATERIAL",
    quoteDate: "2025-06-30",
    status: "HABILITADO",
  },
  {
    id: 4,
    description: "Abrazadera de sujeción metálico para tubo conduit de 1/2\"",
    price: "1.50",
    unit: "pieza",
    abbreviation: "pza.",
    type: "MATERIAL",
    quoteDate: "2021-07-21",
    status: "HABILITADO",
  },
  {
    id: 5,
    description: "Abrazadera de sujeción para letrero vial",
    price: "20.00",
    unit: "pieza",
    abbreviation: "pza.",
    type: "MATERIAL",
    quoteDate: "2021-03-30",
    status: "HABILITADO",
  },
];

const pagination = [1, 2, 3, 4, 5];

export default function DashboardPage() {
  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Insumos</CardTitle>
                <CardDescription>
                  Catálogo visible con búsqueda rápida y paginación resumida.
                </CardDescription>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button variant="outline" className="rounded-full border-border/70 bg-background/80">
                <SlidersHorizontal data-icon="inline-start" />
                Filtros
              </Button>
              <Button variant="outline" className="rounded-full border-border/70 bg-background/80">
                <FileText data-icon="inline-start" />
                Exportar
              </Button>
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex items-end gap-3">
              <div className="flex flex-col gap-2">
                <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Mostrar
                </span>
                <div className="relative">
                  <select className="h-12 min-w-32 appearance-none rounded-2xl border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none transition focus:border-foreground/20">
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>
            </div>

            <div className="flex w-full max-w-sm flex-col gap-2">
              <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscar
              </span>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Buscar insumo"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                />
              </div>
            </div>
          </div>

          <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
            <div className="overflow-x-auto">
              <table className="min-w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-border/70 bg-muted/30 text-left">
                    <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Precio</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Unidad de medida</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Abreviatura</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Tipo insumo</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Fecha cotización</th>
                    <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {supplyRows.map((row, index) => (
                    <tr key={row.id} className={index < supplyRows.length - 1 ? "border-b border-border/60" : ""}>
                      <td className="px-5 py-4 align-top text-foreground">{row.id}</td>
                      <td className="px-5 py-4 align-top text-foreground">
                        <div className="max-w-[260px] leading-7">{row.description}</div>
                      </td>
                      <td className="px-5 py-4 align-top text-foreground">{row.price}</td>
                      <td className="px-5 py-4 align-top text-muted-foreground">{row.unit}</td>
                      <td className="px-5 py-4 align-top text-muted-foreground">{row.abbreviation}</td>
                      <td className="px-5 py-4 align-top">
                        <Badge variant="outline" className="rounded-full border-border/70 bg-muted/30 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-foreground">
                          {row.type}
                        </Badge>
                      </td>
                      <td className="px-5 py-4 align-top text-muted-foreground">{row.quoteDate}</td>
                      <td className="px-5 py-4 align-top">
                        <Badge className="rounded-full bg-emerald-600 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-white">
                          {row.status}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <Separator className="bg-border/70" />

            <div className="flex flex-col gap-4 px-5 py-4 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
              <p>Mostrando registros del 1 al 5 de un total de 3,821 registros</p>

              <div className="flex items-center gap-2 self-end lg:self-auto">
                <Button variant="ghost" size="sm" className="rounded-full text-muted-foreground">
                  <ChevronLeft data-icon="inline-start" />
                  Anterior
                </Button>
                <div className="flex items-center gap-1">
                  {pagination.map((page) => (
                    <Button
                      key={page}
                      variant={page === 1 ? "default" : "ghost"}
                      size="icon-sm"
                      className={page === 1 ? "rounded-full bg-foreground text-background" : "rounded-full text-muted-foreground"}
                    >
                      {page}
                    </Button>
                  ))}
                  <span className="px-2 text-muted-foreground">...</span>
                  <Button variant="ghost" size="icon-sm" className="rounded-full text-muted-foreground">
                    765
                  </Button>
                </div>
                <Button variant="ghost" size="sm" className="rounded-full text-muted-foreground">
                  Siguiente
                  <ChevronRight data-icon="inline-end" />
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

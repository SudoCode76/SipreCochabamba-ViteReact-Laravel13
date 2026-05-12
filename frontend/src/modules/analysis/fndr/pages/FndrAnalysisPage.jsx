import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { ChevronLeft, ChevronRight, Loader2, TrendingUp, Search, MoreHorizontal, RefreshCw } from "lucide-react";
import { createPortal } from "react-dom";
import { useNavigate } from "react-router-dom";
import { ChevronLeft, ChevronRight, Loader2, TrendingUp, Search, MoreHorizontal, RefreshCw, X } from "lucide-react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { itemsService } from "@/modules/dashboard/services/items.service";
import apiClient from "@/lib/api/client";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const statusLabel = {
  AC: "HABILITADO",
  DC: "INHABILITADO",
};

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function FndrAnalysisPage() {
  const navigate = useNavigate();
  const [perPage, setPerPage] = useState(10);
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);
  const [recalculateOpen, setRecalculateOpen] = useState(false);
  const [recalculateItem, setRecalculateItem] = useState(null);
  const [recalculateDate, setRecalculateDate] = useState("");
  const [recalculateStatus, setRecalculateStatus] = useState(null);

  const recalculateMutation = useMutation({
    mutationFn: async ({ itemId, fecha }) => {
      const response = await apiClient.post(`/v1/items/${itemId}/price-recalculation`, {
        fecha,
        mode: "fndr",
      });
      return response.data;
    },
    onSuccess: () => {
      setRecalculateStatus({ type: "success", message: "Precio recalculado correctamente" });
    },
    onError: (error) => {
      const message = error.response?.data?.message || "Error al recalcular el precio"
      setRecalculateStatus({ type: "error", message })
    },
  })

  const handleRecalculate = (item) => {
    setRecalculateItem(item)
    setRecalculateDate(new Date().toISOString().split("T")[0])
    setRecalculateStatus(null)
    setRecalculateOpen(true)
  }

  const handleSubmitRecalculate = (e) => {
    e.preventDefault()
    recalculateMutation.mutate({ itemId: recalculateItem.id_item, fecha: recalculateDate })
  }

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["fndr-items", { page, perPage, search }],
    queryFn: () => itemsService.fndrList({ page, perPage, search }),
    placeholderData: (previousData) => previousData,
  });

  const items = data?.data?.items ?? [];
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));

  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, i) => first + i);
  }, [meta.current_page, totalPages]);

  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);

  const handleSearchSubmit = (event) => {
    event.preventDefault();
    setPage(1);
    setSearch(searchQuery.trim());
  };

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur-md">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                <TrendingUp className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Análisis FNDR</CardTitle>
              </div>
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
                  <select
                    className="h-12 min-w-32 appearance-none rounded-2xl border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none transition focus:border-foreground/20"
                    value={perPage}
                    onChange={handlePerPageChange}
                  >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>
            </div>

            <form className="flex w-full max-w-sm flex-col gap-2" onSubmit={handleSearchSubmit}>
              <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscar
              </span>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Buscar item FNDR"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                />
              </div>
            </form>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando items FNDR...
            </div>
          )}

          {isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {error?.response?.data?.message || "No se pudieron cargar los items FNDR."}
              </AlertDescription>
            </Alert>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Grupo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Subgrupo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Item</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Precio</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Unidad</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item, index) => (
                      <tr key={item.id_item} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-5 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.group?.name ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.subgroup?.description ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          <div className="max-w-[260px]">
                            <span className="font-medium text-xs text-muted-foreground">{item.codigo_item ?? "-"}</span>
                            <div className="leading-7">{item.name ?? "-"}</div>
                          </div>
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {item.calculated_price !== null ? Number(item.calculated_price).toFixed(2) : "-"}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.unit_measure?.abbreviation ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[item.status] || "bg-slate-500 text-white"}`}>
                            {item.status_label ?? "-"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                <span>Análisis de Precio</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                onClick={() => handleRecalculate(item)}
                              >
                                <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                <span>Recalcular Precio</span>
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
                    {items.length === 0 && (
                      <tr>
                        <td colSpan={8} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron items FNDR para los filtros seleccionados.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <Separator className="bg-border/70" />

              <div className="flex flex-col gap-4 px-5 py-4 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                <p>Mostrando registros del {startRecord} al {endRecord} de un total de {meta.total} registros</p>

                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                    disabled={meta.current_page <= 1 || isFetching}
                  >
                    <ChevronLeft data-icon="inline-start" />
                    Anterior
                  </Button>
                  <div className="flex items-center gap-1">
                    {visiblePages.map((pageNumber) => (
                      <Button
                        key={pageNumber}
                        variant={pageNumber === meta.current_page ? "default" : "ghost"}
                        size="icon-sm"
                        className={pageNumber === meta.current_page ? "rounded-full bg-foreground text-background" : "rounded-full text-muted-foreground"}
                        onClick={() => setPage(pageNumber)}
                        disabled={isFetching}
                      >
                        {pageNumber}
                      </Button>
                    ))}
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={meta.current_page >= totalPages || isFetching}
                  >
                    Siguiente
                    <ChevronRight data-icon="inline-end" />
                  </Button>
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {recalculateOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Recalcular precio Item</CardTitle>
                    <CardDescription>Recalcular Precios Unitarios por periodos de tiempo</CardDescription>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={() => setRecalculateOpen(false)}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {recalculateStatus && (
                  <Alert className="mb-4" variant={recalculateStatus.type === "error" ? "destructive" : "default"}>
                    <AlertDescription>{recalculateStatus.message}</AlertDescription>
                  </Alert>
                )}

                <form className="flex flex-col gap-5" onSubmit={handleSubmitRecalculate}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="item_recalculate" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</Label>
                    <Input id="item_recalculate" value={recalculateItem?.name ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="fecha" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Seleccione Fecha de Impresion/calculo</Label>
                    <Input id="fecha" type="date" value={recalculateDate} onChange={(e) => setRecalculateDate(e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={recalculateMutation.isPending}>
                    {recalculateMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Recalculando...
                      </>
                    ) : "Recalcular"}
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}

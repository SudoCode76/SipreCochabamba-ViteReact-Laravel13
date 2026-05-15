import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { FileText, Search, ChevronLeft, ChevronRight, Loader2, MoreHorizontal, Pencil, File, X } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import apiClient from "@/lib/api/client";

const approvalStatusClass = {
  PD: "bg-amber-500 text-white",
  AP: "bg-emerald-600 text-white",
  RC: "bg-rose-600 text-white",
};

export default function InputRequestsPage() {
  const navigate = useNavigate();
  const [perPage, setPerPage] = useState(15);
  const [page, setPage] = useState(1);
  const [viewOpen, setViewOpen] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState(null);
  const [viewPerPage, setViewPerPage] = useState(25);
  const [viewPage, setViewPage] = useState(1);
  const [viewSearch, setViewSearch] = useState("");

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["input-requests", { page, perPage }],
    queryFn: async () => {
      const response = await apiClient.get("/v1/input-requests", {
        params: { page, per_page: perPage },
      });
      return response.data;
    },
    placeholderData: (previousData) => previousData,
  });

  const items = data?.data?.items ?? [];
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));

  const quoteHistoryQuery = useQuery({
    queryKey: ["input-request-quotes", selectedRequest?.id_solicitud],
    queryFn: async () => {
      const response = await apiClient.get(`/v1/input-requests/${selectedRequest.id_solicitud}/quotes/history`);
      return response.data;
    },
    enabled: viewOpen && Boolean(selectedRequest?.id_solicitud),
  });

  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, i) => first + i);
  }, [meta.current_page, totalPages]);

  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);

  const quoteHistory = quoteHistoryQuery.data?.data?.items ?? [];
  const filteredQuoteHistory = useMemo(() => {
    const search = viewSearch.trim().toLowerCase();

    if (!search) {
      return quoteHistory;
    }

    return quoteHistory.filter((quote) => (
      String(quote.id_cotizacion ?? "").toLowerCase().includes(search)
      || String(quote.fecha ?? "").toLowerCase().includes(search)
      || String(quote.condicion ?? "").toLowerCase().includes(search)
    ));
  }, [quoteHistory, viewSearch]);

  const quoteTotalPages = Math.max(1, Math.ceil(filteredQuoteHistory.length / viewPerPage));
  const safeQuotePage = Math.min(viewPage, quoteTotalPages);
  const visibleQuoteHistory = filteredQuoteHistory.slice((safeQuotePage - 1) * viewPerPage, safeQuotePage * viewPerPage);
  const quoteStartRecord = filteredQuoteHistory.length === 0 ? 0 : (safeQuotePage - 1) * viewPerPage + 1;
  const quoteEndRecord = Math.min(safeQuotePage * viewPerPage, filteredQuoteHistory.length);

  const handleView = (item) => {
    setSelectedRequest(item);
    setViewPerPage(25);
    setViewPage(1);
    setViewSearch("");
    setViewOpen(true);
  };

  const closeView = () => {
    setViewOpen(false);
    setSelectedRequest(null);
    setViewPage(1);
    setViewSearch("");
  };

  const getQuoteLabel = (index, prefix) => {
    const isCurrent = index === 0;
    return `${prefix} ${isCurrent ? "vigente" : "anterior"}`;
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <FileText className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Listar Solicitud de Insumo</CardTitle>
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
                    onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1); }}
                  >
                    <option value={10}>10</option>
                    <option value={15}>15</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>
            </div>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando solicitudes...
            </div>
          )}

          {isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {error?.response?.data?.message || "Error al cargar las solicitudes."}
              </AlertDescription>
            </Alert>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-3 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Descripción</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Precio</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Unidad</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Abreviatura</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Tipo</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Fecha</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Solicitante</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Ubicación</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Justificación</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Motivo</th>
                      <th className="px-3 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item, index) => (
                      <tr key={item.id_solicitud} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-3 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-3 py-4 align-top text-foreground max-w-[150px]">
                          <div className="truncate">{item.descripcion}</div>
                        </td>
                        <td className="px-3 py-4 align-top text-foreground">
                          {Number(item.precio).toFixed(2)}
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground max-w-[100px]">
                          <div className="truncate">{item.nombre_unidad_medida}</div>
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground">
                          {item.abreviatura}
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground">
                          {item.nombre_tipo}
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground">
                          {item.fecha}
                        </td>
                        <td className="px-3 py-4 align-top text-foreground max-w-[120px]">
                          <div className="truncate">{item.nombre_completo}</div>
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground max-w-[100px]">
                          <div className="truncate">{item.ubicacion || "-"}</div>
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground max-w-[120px]">
                          <div className="truncate">{item.justificacion || "-"}</div>
                        </td>
                        <td className="px-3 py-4 align-top">
                          <Badge className={`rounded-full px-2 py-0.5 text-[10px] uppercase tracking-[0.18em] ${approvalStatusClass[item.approval_status] || "bg-slate-500 text-white"}`}>
                            {item.approval_status_label || item.approval_status}
                          </Badge>
                        </td>
                        <td className="px-3 py-4 align-top text-muted-foreground max-w-[100px]">
                          <div className="truncate">{item.notificacion || "-"}</div>
                        </td>
                        <td className="px-3 py-4 align-top text-center">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              {item.available_actions?.edit && (
                                <DropdownMenuItem
                                  className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2"
                                  onClick={() => navigate(`/Listar Solicitud de Insumo/${item.id_solicitud}/editar`)}
                                >
                                  <Pencil className="h-4 w-4 text-muted-foreground" />
                                  <span>Editar</span>
                                </DropdownMenuItem>
                              )}
                              {item.available_actions?.view_quotes && (
                                <>
                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                   <DropdownMenuItem
                                     className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2"
                                     onClick={() => handleView(item)}
                                   >
                                     <File className="h-4 w-4 text-muted-foreground" />
                                     <span>Ver Cotizaciones</span>
                                   </DropdownMenuItem>
                                </>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
                    {items.length === 0 && (
                      <tr>
                        <td colSpan={13} className="px-3 py-8 text-center text-muted-foreground">
                          No se encontraron solicitudes.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

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

      {viewOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-6xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <CardTitle className="text-center text-4xl tracking-[-0.04em] text-muted-foreground">
                      Ver Cotización Actual
                    </CardTitle>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeView}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="space-y-6 px-6 pb-6 pt-2 sm:px-8">
            <div className="space-y-2">
              <span className="text-base text-foreground">Insumo</span>
              <Input value={selectedRequest?.descripcion ?? ""} className="h-12 rounded-none border-border/80 bg-background/90" disabled />
            </div>

            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="flex flex-col gap-2">
                <span className="text-base text-foreground">Show</span>
                <div className="relative w-44">
                  <select
                    className="h-12 w-full appearance-none rounded-none border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none"
                    value={viewPerPage}
                    onChange={(event) => {
                      setViewPerPage(Number(event.target.value));
                      setViewPage(1);
                    }}
                  >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
                <span className="text-base text-foreground">entries</span>
              </div>

              <div className="flex w-full max-w-xs flex-col gap-2 self-start lg:self-auto">
                <span className="text-right text-base text-foreground">Search:</span>
                <div className="relative">
                  <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    value={viewSearch}
                    onChange={(event) => {
                      setViewSearch(event.target.value);
                      setViewPage(1);
                    }}
                    className="h-12 rounded-none border-border/80 bg-background/90 pl-11"
                  />
                </div>
              </div>
            </div>

            <div className="overflow-hidden border border-border/70 bg-background/90">
              <div className="overflow-x-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/15 text-left">
                      <th className="px-3 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Fecha</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Propuesta oficial</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Propuesta Alternativa 1</th>
                      <th className="px-3 py-4 font-semibold text-foreground">Propuesta Alternativa 2</th>
                    </tr>
                  </thead>
                  <tbody>
                    {quoteHistoryQuery.isLoading && (
                      <tr>
                        <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                          <Loader2 className="mr-2 inline size-4 animate-spin" /> Cargando cotizaciones...
                        </td>
                      </tr>
                    )}
                    {!quoteHistoryQuery.isLoading && visibleQuoteHistory.map((quote, index) => {
                      const absoluteIndex = quoteStartRecord + index - 1;

                      return (
                        <tr key={quote.id_cotizacion} className={index < visibleQuoteHistory.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-3 py-4 align-top text-muted-foreground">{quoteStartRecord + index}</td>
                          <td className="px-3 py-4 align-top text-muted-foreground">{quote.fecha ?? "-"}</td>
                          <td className="px-3 py-4 align-top">
                            {quote.archivo ? <a href={`/storage/${quote.archivo}`} target="_blank" rel="noreferrer" className="text-sky-500 hover:underline">{getQuoteLabel(absoluteIndex, "propuesta oficial")}</a> : <span className="text-muted-foreground">Sin archivo</span>}
                          </td>
                          <td className="px-3 py-4 align-top">
                            {quote.archivo1 ? <a href={`/storage/${quote.archivo1}`} target="_blank" rel="noreferrer" className="text-sky-500 hover:underline">{getQuoteLabel(absoluteIndex, "propuesta alternativa")}</a> : <span className="text-muted-foreground">Sin archivo</span>}
                          </td>
                          <td className="px-3 py-4 align-top">
                            {quote.archivo2 ? <a href={`/storage/${quote.archivo2}`} target="_blank" rel="noreferrer" className="text-sky-500 hover:underline">{getQuoteLabel(absoluteIndex, "propuesta alternativa")}</a> : <span className="text-muted-foreground">Sin archivo</span>}
                          </td>
                        </tr>
                      );
                    })}
                    {quoteHistoryQuery.isError && (
                      <tr>
                        <td colSpan={5} className="px-3 py-8 text-center text-destructive">
                          {quoteHistoryQuery.error?.response?.data?.message || "No se pudo cargar el historial de cotizaciones."}
                        </td>
                      </tr>
                    )}
                    {!quoteHistoryQuery.isLoading && visibleQuoteHistory.length === 0 && (
                      <tr>
                        <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                          No hay cotizaciones registradas.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-col gap-4 border-t border-border/70 px-3 py-4 text-sm lg:flex-row lg:items-center lg:justify-between">
                <p className="text-foreground">
                  Showing {quoteStartRecord} to {quoteEndRecord} of {filteredQuoteHistory.length} entries
                </p>

                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button
                    type="button"
                    variant="ghost"
                    className="rounded-none px-2 text-foreground"
                    onClick={() => setViewPage((prev) => Math.max(1, prev - 1))}
                    disabled={safeQuotePage <= 1}
                  >
                    Previous
                  </Button>
                  <Button type="button" variant="outline" className="rounded-none px-3" disabled>
                    {safeQuotePage}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    className="rounded-none px-2 text-foreground"
                    onClick={() => setViewPage((prev) => Math.min(quoteTotalPages, prev + 1))}
                    disabled={safeQuotePage >= quoteTotalPages}
                  >
                    Next
                  </Button>
                </div>
              </div>
            </div>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}

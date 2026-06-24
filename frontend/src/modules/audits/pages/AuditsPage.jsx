import { useDeferredValue, useMemo, useState } from "react";
import { CalendarDays, ChevronLeft, ChevronRight, Filter, Loader2, RefreshCcw, ShieldCheck } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ClearableSearchInput } from "@/components/ui/clearable-search-input";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn, formatDateTime } from "@/lib/utils";
import { auditsService } from "@/modules/audits/services/audits.service";

export default function AuditsPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [userTerm, setUserTerm] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const deferredSearch = useDeferredValue(searchTerm.trim());
  const deferredUser = useDeferredValue(userTerm.trim());

  const auditsQuery = useQuery({
    queryKey: ["audits", { page, perPage, deferredSearch, deferredUser, dateFrom, dateTo }],
    queryFn: () => auditsService.list({
      page,
      perPage,
      search: deferredSearch,
      user: deferredUser,
      dateFrom,
      dateTo,
    }),
    placeholderData: (previousData) => previousData,
  });

  const audits = useMemo(() => auditsQuery.data?.data?.items ?? [], [auditsQuery.data]);
  const meta = auditsQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);
  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <ShieldCheck className="size-5" />
              </div>
              <CardTitle className="text-2xl tracking-[-0.04em]">Auditoria del sistema</CardTitle>
            </div>
            <Button variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={() => auditsQuery.refetch()} disabled={auditsQuery.isFetching}>
              <RefreshCcw data-icon="inline-start" className={cn(auditsQuery.isFetching && "animate-spin")} />
              Refrescar
            </Button>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          <div className="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.8fr)_repeat(2,minmax(0,0.45fr))] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="auditSearch" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Accion</Label>
              <ClearableSearchInput
                id="auditSearch"
                value={searchTerm}
                onChange={(event) => { setSearchTerm(event.target.value); setPage(1); }}
                onClear={() => { setSearchTerm(""); setPage(1); }}
                placeholder="Buscar por texto de auditoria"
                className="h-12 rounded-2xl border-border/80 bg-background/90"
                isLoading={auditsQuery.isFetching && !auditsQuery.isLoading}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="auditUser" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Usuario</Label>
              <ClearableSearchInput
                id="auditUser"
                value={userTerm}
                onChange={(event) => { setUserTerm(event.target.value); setPage(1); }}
                onClear={() => { setUserTerm(""); setPage(1); }}
                placeholder="Buscar usuario"
                className="h-12 rounded-2xl border-border/80 bg-background/90"
                isLoading={auditsQuery.isFetching && !auditsQuery.isLoading}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="dateFrom" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Desde</Label>
              <Input id="dateFrom" type="date" value={dateFrom} onChange={(event) => { setDateFrom(event.target.value); setPage(1); }} className="h-12 rounded-2xl border-border/80 bg-background/90" />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="dateTo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Hasta</Label>
              <Input id="dateTo" type="date" value={dateTo} onChange={(event) => { setDateTo(event.target.value); setPage(1); }} className="h-12 rounded-2xl border-border/80 bg-background/90" />
            </div>

            <div className="xl:col-span-4 flex items-center gap-3">
              <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                <Filter className="size-3.5" />
                Mostrar
              </div>
              <select className="h-11 min-w-28 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none" value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }}>
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>

          {auditsQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando auditoria...
            </div>
          )}

          {auditsQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {auditsQuery.error?.response?.status === 403
                  ? "Solo un administrador puede consultar la auditoria."
                  : auditsQuery.error?.response?.data?.message || "No se pudo cargar la auditoria."}
              </AlertDescription>
            </Alert>
          )}

          {!auditsQuery.isLoading && !auditsQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-border/70 text-sm">
                    <thead className="bg-muted/30 text-left text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                      <tr>
                        <th className="px-5 py-4">Usuario</th>
                        <th className="px-5 py-4">Fecha y hora</th>
                        <th className="px-5 py-4">IP</th>
                        <th className="px-5 py-4">Accion</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60">
                      {audits.map((audit) => (
                        <tr key={audit.id}>
                          <td className="px-5 py-4 font-medium text-foreground">{audit.user_name || "-"}</td>
                          <td className="px-5 py-4 text-muted-foreground">{formatDateTime(audit.occurred_at)}</td>
                          <td className="px-5 py-4 text-muted-foreground">{audit.ip || "-"}</td>
                          <td className="px-5 py-4 text-foreground">{audit.process}</td>
                        </tr>
                      ))}
                      {audits.length === 0 && (
                        <tr>
                          <td colSpan={4} className="px-5 py-8 text-center text-muted-foreground">No se encontraron registros para los filtros seleccionados.</td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="grid gap-4 lg:hidden">
                {audits.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">No se encontraron registros para los filtros seleccionados.</CardContent>
                  </Card>
                ) : audits.map((audit) => (
                  <Card key={audit.id} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-3 p-5">
                      <p className="font-medium text-foreground">{audit.user_name || "-"}</p>
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CalendarDays className="size-4" />
                        {formatDateTime(audit.occurred_at)}
                      </div>
                      <p className="text-sm text-muted-foreground">IP: {audit.ip || "-"}</p>
                      <p className="text-sm text-foreground">{audit.process}</p>
                    </CardContent>
                  </Card>
                ))}
              </div>

              <div className="flex flex-col gap-4 px-1 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                <p>Mostrando registros del {startRecord} al {endRecord} de un total de {meta.total} registros</p>
                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button variant="ghost" size="sm" className="rounded-full text-muted-foreground" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={meta.current_page <= 1 || auditsQuery.isFetching}>
                    <ChevronLeft data-icon="inline-start" />
                    Anterior
                  </Button>
                  <div className="flex items-center gap-1">
                    {visiblePages.map((pageNumber) => (
                      <Button key={pageNumber} variant={pageNumber === meta.current_page ? "default" : "ghost"} size="icon-sm" className={pageNumber === meta.current_page ? "rounded-full bg-foreground text-background" : "rounded-full text-muted-foreground"} onClick={() => setPage(pageNumber)} disabled={auditsQuery.isFetching}>
                        {pageNumber}
                      </Button>
                    ))}
                  </div>
                  <Button variant="ghost" size="sm" className="rounded-full text-muted-foreground" onClick={() => setPage((current) => Math.min(totalPages, current + 1))} disabled={meta.current_page >= totalPages || auditsQuery.isFetching}>
                    Siguiente
                    <ChevronRight data-icon="inline-end" />
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
